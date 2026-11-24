<?php

/*
 * This file is part of fof/first-post-approval.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\FirstPostApproval\Tests\integration\Api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Flags\Flag;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\FirstPostApproval\Tests\integration\ExtensionDepsTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ApprovalCountTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use ExtensionDepsTrait;

    protected const NEW_USER = 4;
    protected const MODERATOR = 5;

    public function setUp(): void
    {
        parent::setUp();

        $this->extensionDeps();

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 1, 'title' => __CLASS__, 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 3, 'first_post_id' => 1, 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now()->subDay()->toDateTimeString(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t></t>'],
            ],
            User::class => [
                ['id' => 3, 'username' => 'establishedUser', 'email' => 'established@machine.local', 'is_email_confirmed' => true, 'first_discussion_approval_count' => 10, 'first_post_approval_count' => 20],
                ['id' => self::NEW_USER, 'username' => 'newUser', 'email' => 'newuser@machine.local', 'is_email_confirmed' => true],
                ['id' => self::MODERATOR, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'is_email_confirmed' => true],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MEMBER_ID],
                ['user_id' => self::NEW_USER, 'group_id' => Group::MEMBER_ID],
                ['user_id' => self::MODERATOR, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'bypassTagCounts', 'created_at' => Carbon::now()->toDateTimeString()],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle', 'created_at' => Carbon::now()->toDateTimeString()],
            ],
        ]);
    }

    public static function approvers(): array
    {
        return [
            'moderator' => [self::MODERATOR],
            'admin'     => [1],
        ];
    }

    #[Test]
    #[DataProvider('approvers')]
    public function approvingAPendingReplyCountsAndClearsItsFlag(int $approver)
    {
        $postId = $this->reply();

        $this->assertPending($postId);

        $this->patchPost($postId, ['isApproved' => true], $approver);

        $this->assertCounts(discussions: 0, posts: 1);
        $this->assertSame(0, $this->approvalFlagCount($postId));
    }

    #[Test]
    #[DataProvider('approvers')]
    public function approvingAPendingDiscussionCountsAndClearsItsFlag(int $approver)
    {
        [$discussionId, $postId] = $this->startDiscussion();

        $this->assertPending($postId);
        $this->assertFalse(Discussion::find($discussionId)->is_approved);

        $this->patchPost($postId, ['isApproved' => true], $approver);

        $this->assertTrue(Discussion::find($discussionId)->is_approved);
        $this->assertCounts(discussions: 1, posts: 0);
        $this->assertSame(0, $this->approvalFlagCount($postId));
    }

    #[Test]
    public function theMemberPostsFreelyOnceTheThresholdIsReached()
    {
        $this->setting('fof-first-post-approval.discussionCount', 1);
        $this->setting('fof-first-post-approval.postCount', 2);

        [, $firstPost] = $this->startDiscussion();
        $this->patchPost($firstPost, ['isApproved' => true]);

        // A discussion counts towards both thresholds: one more reply is still held.
        $held = $this->reply();
        $this->assertPending($held);
        $this->patchPost($held, ['isApproved' => true]);

        $this->assertCounts(discussions: 1, posts: 1);

        $reply = $this->reply();
        [$discussionId, $discussionPost] = $this->startDiscussion();

        $this->assertTrue(Post::find($reply)->is_approved);
        $this->assertTrue(Post::find($discussionPost)->is_approved);
        $this->assertTrue(Discussion::find($discussionId)->is_approved);
        $this->assertSame(0, Flag::query()->whereIn('post_id', [$reply, $discussionPost])->count());
    }

    #[Test]
    public function hidingAPendingReplyDoesNotCountAndClearsItsFlag()
    {
        $postId = $this->reply();

        $this->patchPost($postId, ['isHidden' => true]);

        $this->assertCounts(discussions: 0, posts: 0);
        $this->assertSame(0, $this->approvalFlagCount($postId));
        $this->assertPending($this->reply(), 'The next reply should still be held.');
    }

    #[Test]
    public function hidingAPendingFirstPostDoesNotCount()
    {
        [$discussionId, $postId] = $this->startDiscussion();

        $this->patchPost($postId, ['isHidden' => true]);

        $this->assertCounts(discussions: 0, posts: 0);
        $this->assertFalse(Discussion::find($discussionId)->is_approved);
        $this->assertSame(0, $this->approvalFlagCount($postId));
    }

    #[Test]
    public function restoringAHiddenPendingFirstPostCounts()
    {
        [$discussionId, $postId] = $this->startDiscussion();

        $this->patchPost($postId, ['isHidden' => true]);
        $this->patchPost($postId, ['isHidden' => false]);

        $this->assertTrue(Discussion::find($discussionId)->is_approved);
        $this->assertCounts(discussions: 1, posts: 0);
        $this->assertSame(0, $this->approvalFlagCount($postId));
    }

    #[Test]
    public function approvingAHiddenPendingFirstPostCounts()
    {
        [$discussionId, $postId] = $this->startDiscussion();

        $this->patchPost($postId, ['isHidden' => true]);

        $this->assertTrue(Post::find($postId)->is_approved);
        $this->assertNotNull(Post::find($postId)->hidden_at);
        $this->assertFalse(Discussion::find($discussionId)->is_approved);

        $this->patchPost($postId, ['isApproved' => true]);

        $this->assertNotNull(Post::find($postId)->hidden_at, 'Approving should not restore the post.');
        $this->assertTrue(Discussion::find($discussionId)->is_approved);
        $this->assertCounts(discussions: 1, posts: 0);
    }

    #[Test]
    public function approvingAPendingReplyItsAuthorHidCounts()
    {
        $this->setting('allow_hide_own_posts', '-1');

        $postId = $this->reply();

        $this->patchPost($postId, ['isHidden' => true], self::NEW_USER);

        // The author may not approve, so hiding leaves the reply pending.
        $this->assertPending($postId);
        $this->assertNotNull(Post::find($postId)->hidden_at);
        $this->assertCounts(discussions: 0, posts: 0);

        $this->patchPost($postId, ['isApproved' => true]);

        $this->assertNotNull(Post::find($postId)->hidden_at);
        $this->assertCounts(discussions: 0, posts: 1);
        $this->assertSame(0, $this->approvalFlagCount($postId));
    }

    #[Test]
    public function approvingLeavesNoApprovalFlagWhenCoreApprovalAlsoHoldsThePost()
    {
        $this->database()->table('group_permission')
            ->where('group_id', Group::MEMBER_ID)
            ->whereIn('permission', ['discussion.replyWithoutApproval', 'discussion.startWithoutApproval'])
            ->delete();

        $replyId = $this->reply();
        [, $firstPostId] = $this->startDiscussion();

        // One flag from core's approval, one from this extension.
        $this->assertSame(2, $this->approvalFlagCount($replyId));
        $this->assertSame(2, $this->approvalFlagCount($firstPostId));

        $this->patchPost($replyId, ['isApproved' => true]);
        $this->patchPost($firstPostId, ['isApproved' => true]);

        $this->assertSame(0, $this->approvalFlagCount($replyId));
        $this->assertSame(0, $this->approvalFlagCount($firstPostId));
        $this->assertCounts(discussions: 1, posts: 1);
    }

    protected function reply(): int
    {
        $response = $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => self::NEW_USER,
                'json'            => [
                    'data' => [
                        'attributes'    => ['content' => 'A reply from a new member'],
                        'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        return (int) json_decode((string) $response->getBody(), true)['data']['id'];
    }

    /**
     * @return array{int, int} discussion id, first post id
     */
    protected function startDiscussion(): array
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => self::NEW_USER,
                'json'            => [
                    'data' => [
                        'attributes' => ['title' => 'A new member\'s discussion', 'content' => 'Its first post'],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $id = (int) json_decode((string) $response->getBody(), true)['data']['id'];

        return [$id, Discussion::find($id)->first_post_id];
    }

    protected function patchPost(int $postId, array $attributes, int $actor = self::MODERATOR): void
    {
        $response = $this->send(
            $this->request('PATCH', "/api/posts/$postId", [
                'authenticatedAs' => $actor,
                'json'            => ['data' => ['type' => 'posts', 'id' => (string) $postId, 'attributes' => $attributes]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    protected function assertPending(int $postId, string $message = ''): void
    {
        $this->assertFalse(Post::find($postId)->is_approved, $message);
        $this->assertGreaterThan(0, $this->approvalFlagCount($postId), $message);
    }

    protected function assertCounts(int $discussions, int $posts): void
    {
        $user = User::find(self::NEW_USER);

        $this->assertSame(
            ['discussions' => $discussions, 'posts' => $posts],
            ['discussions' => $user->first_discussion_approval_count, 'posts' => $user->first_post_approval_count]
        );
    }

    protected function approvalFlagCount(int $postId): int
    {
        return Flag::query()->where('post_id', $postId)->where('type', 'approval')->count();
    }
}
