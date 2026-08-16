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
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\FirstPostApproval\Tests\integration\ExtensionDepsTrait;
use PHPUnit\Framework\Attributes\Test;
use Flarum\User\User;

class PrivateDiscussionTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use ExtensionDepsTrait;

    public function setUp(): void
    {
        parent::setUp();

        $this->extensionDeps();

        // Tags is only enabled as a dependency of Byobu, so don't require a tag to start a discussion
        $this->setting('flarum-tags.min_primary_tags', 0);

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'establishedUser', 'email' => 'established@machine.local', 'is_email_confirmed' => true, 'first_discussion_approval_count' => 10, 'first_post_approval_count' => 20, 'discussion_count' => 10, 'comment_count' => 20],
                ['id' => 4, 'username' => 'newUser', 'email' => 'newuser@machine.local', 'is_email_confirmed' => true, 'first_discussion_approval_count' => 0, 'first_post_approval_count' => 0, 'discussion_count' => 0, 'comment_count' => 0],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MEMBER_ID],
                ['user_id' => 4, 'group_id' => Group::MEMBER_ID],
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'discussion.startPrivateDiscussionWithUsers', 'created_at' => Carbon::now()->toDateTimeString()],
                ['group_id' => Group::MEMBER_ID, 'permission' => 'discussion.startPrivateDiscussionWithGroups', 'created_at' => Carbon::now()->toDateTimeString()],
            ],
        ]);
    }

    protected function createPrivateDiscussion(int $actorId, int $recipientId)
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
                                'data' => [
                                    ['type' => 'users', 'id' => (string) $recipientId],
                                ],
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
