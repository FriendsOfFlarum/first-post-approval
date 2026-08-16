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
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\FirstPostApproval\Tests\integration\ExtensionDepsTrait;
use PHPUnit\Framework\Attributes\Test;

class PrivateDiscussionTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use ExtensionDepsTrait;

    public function setUp(): void
    {
        parent::setUp();

        $this->extensionDeps();

        $this->prepareDatabase([
            Discussion::class => [
                // Existing private discussion owned by the user subject to approval
                ['id' => 100, 'title' => __CLASS__, 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 4, 'first_post_id' => 100, 'is_private' => true],
            ],
            Post::class => [
                ['id' => 100, 'discussion_id' => 100, 'number' => 1, 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 4, 'type' => 'comment', 'content' => '<t></t>'],
            ],
            'recipients' => [
                ['discussion_id' => 100, 'user_id' => 4, 'created_at' => Carbon::now()->toDateTimeString(), 'updated_at' => Carbon::now()->toDateTimeString()],
                ['discussion_id' => 100, 'user_id' => 3, 'created_at' => Carbon::now()->toDateTimeString(), 'updated_at' => Carbon::now()->toDateTimeString()],
            ],
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'establishedUser', 'email' => 'established@machine.local', 'is_email_confirmed' => true, 'first_discussion_approval_count' => 10, 'first_post_approval_count' => 20, 'discussion_count' => 10, 'comment_count' => 20],
                ['id' => 4, 'username' => 'newUser', 'email' => 'newuser@machine.local', 'is_email_confirmed' => true, 'first_discussion_approval_count' => 0, 'first_post_approval_count' => 0, 'discussion_count' => 0, 'comment_count' => 0],
                ['id' => 5, 'username' => 'bystander', 'email' => 'bystander@machine.local', 'is_email_confirmed' => true, 'discussion_count' => 10, 'comment_count' => 20],
                ['id' => 6, 'username' => 'otherBystander', 'email' => 'other@machine.local', 'is_email_confirmed' => true, 'discussion_count' => 10, 'comment_count' => 20],
                // New user, but holds the bypass permission via the moderator group
                ['id' => 7, 'username' => 'newButExempt', 'email' => 'exempt@machine.local', 'is_email_confirmed' => true, 'first_discussion_approval_count' => 0, 'first_post_approval_count' => 0, 'discussion_count' => 0, 'comment_count' => 0],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 4, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 5, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 6, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 7, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 7, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'discussion.startPrivateDiscussionWithUsers', 'created_at' => Carbon::now()->toDateTimeString()],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'discussion.startPrivateDiscussionWithGroups', 'created_at' => Carbon::now()->toDateTimeString()],
                // Tags is only enabled as a dependency of Byobu. In 2.0 the tags
                // relationship is required on creation unless the actor can
                // bypassTagCounts, so grant that rather than tag every discussion.
                ['group_id' => Group::MEMBER_ID, 'permission' => 'bypassTagCounts', 'created_at' => Carbon::now()->toDateTimeString()],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'discussion.addMoreThanTwoUserRecipients', 'created_at' => Carbon::now()->toDateTimeString()],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'discussion.editUserRecipients', 'created_at' => Carbon::now()->toDateTimeString()],
                // Several tests post as the same user in quick succession, which
                // would otherwise trip the 10s post creation throttle (429).
                ['group_id' => Group::MEMBER_ID, 'permission' => 'postWithoutThrottle', 'created_at' => Carbon::now()->toDateTimeString()],
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'discussion.firstPostWithoutApproval', 'created_at' => Carbon::now()->toDateTimeString()],
            ],
        ]);
    }

    protected function createPrivateDiscussion(int $actorId, int ...$recipientIds)
    {
        return $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => $actorId,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => 'test - too-obscure',
                            'content' => 'predetermined content for automated testing - too-obscure',
                        ],
                        'relationships' => [
                            'recipientUsers' => [
                                'data' => array_map(
                                    fn (int $id) => ['type' => 'users', 'id' => (string) $id],
                                    $recipientIds
                                ),
                            ],
                        ],
                    ],
                ],
            ])
        );
    }

    #[Test]
    public function userSubjectToApprovalCannotStartPrivateDiscussion()
    {
        $response = $this->createPrivateDiscussion(4, 3);

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function establishedUserCanStartPrivateDiscussion()
    {
        $response = $this->createPrivateDiscussion(3, 4);

        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function userSubjectToApprovalCanStartPrivateDiscussionWhenRestrictionDisabled()
    {
        $this->setting('fof-first-post-approval.restrictPrivateDiscussions', false);

        $response = $this->createPrivateDiscussion(4, 3);

        $this->assertEquals(201, $response->getStatusCode());
    }

    /**
     * Byobu checks `addMoreThanTwoUserRecipients` unprefixed and against a
     * Discussion instance, which the gate routes to Discussion model policies
     * only — a global-only policy registration is never consulted there.
     *
     * The check is reached by editing recipients on an existing discussion,
     * which skips the "start a private discussion" checks that would otherwise
     * deny the request first and mask this one.
     */
    #[Test]
    public function userSubjectToApprovalCannotAddManyRecipientsToExistingDiscussion()
    {
        $response = $this->send(
            $this->request('PATCH', '/api/discussions/100', [
                'authenticatedAs' => 4,
                'json'            => [
                    'data' => [
                        'relationships' => [
                            'recipientUsers' => [
                                'data' => [
                                    ['type' => 'users', 'id' => '3'],
                                    ['type' => 'users', 'id' => '5'],
                                    ['type' => 'users', 'id' => '6'],
                                ],
                            ],
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * The bypass permission is stored prefixed, but is checked without a
     * Discussion in the repository, so the prefix has to be spelled out there.
     */
    #[Test]
    public function newUserWithBypassPermissionCanStartPrivateDiscussion()
    {
        $response = $this->createPrivateDiscussion(7, 3);

        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function forumAttributesReflectTheRestriction()
    {
        $response = $this->send(
            $this->request('GET', '/api/', ['authenticatedAs' => 4])
        );

        $attributes = json_decode($response->getBody()->getContents(), true)['data']['attributes'];

        $this->assertFalse($attributes['canStartPrivateDiscussion']);
        $this->assertFalse($attributes['canStartPrivateDiscussionWithUsers']);
        $this->assertFalse($attributes['canStartPrivateDiscussionWithGroups']);
    }

    #[Test]
    public function forumAttributesAllowEstablishedUser()
    {
        $response = $this->send(
            $this->request('GET', '/api/', ['authenticatedAs' => 3])
        );

        $attributes = json_decode($response->getBody()->getContents(), true)['data']['attributes'];

        $this->assertTrue($attributes['canStartPrivateDiscussion']);
        $this->assertTrue($attributes['canStartPrivateDiscussionWithUsers']);
    }
}
