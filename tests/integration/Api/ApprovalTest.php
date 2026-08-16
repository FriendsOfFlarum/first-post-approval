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
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\FirstPostApproval\Tests\integration\ExtensionDepsTrait;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Flarum\Post\Post;
use Flarum\User\User;

class ApprovalTest extends TestCase
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
            Discussion::class => [
                ['id' => 1, 'title' => __CLASS__, 'created_at' => Carbon::now()->toDateTimeString(), 'user_id' => 3, 'first_post_id' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now()->subDay()->toDateTimeString(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t></t>'],
            ],
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'establishedUser', 'email' => 'established@machine.local', 'is_email_confirmed' => true, 'first_discussion_approval_count' => 10, 'first_post_approval_count' => 20],
                ['id' => 4, 'username' => 'newUser', 'email' => 'newuser@machine.local', 'is_email_confirmed' => true, 'first_discussion_approval_count' => 0, 'first_post_approval_count' => 0],
            ],
        ]);
    }

    public static function approvedUsers(): array
    {
        return [
            [1],
            [3],
        ];
    }

    public static function unapprovedUsers(): array
    {
        return [
            [2],
            [4],
        ];
    }

    #[Test]
    #[DataProvider('approvedUsers')]
    public function approvedUsersCanStartDiscussionWithoutApproval(?int $userId)
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => $userId,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => 'test - too-obscure',
                            'content' => 'predetermined content for automated testing - too-obscure',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertIsNumeric($data['data']['id']);
        /** @var Discussion $discussion */
        $discussion = Discussion::find($data['data']['id']);
        $this->assertNotNull($discussion);
        $this->assertEquals('test - too-obscure', $discussion->title);
        $this->assertEquals('test - too-obscure', Arr::get($data, 'data.attributes.title'));
        $this->assertTrue($discussion->is_approved);
        $this->assertTrue($discussion->posts->first()->is_approved);

        $post = $discussion->posts->first();

        $flag = $post->flags->first();

        $this->assertNull($flag);
    }

    #[Test]
    #[DataProvider('approvedUsers')]
    public function approvedUsersCanReplyWithoutApproval(?int $userId)
    {
        $response = $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => $userId,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'content' => 'predetermined content for automated testing - too-obscure',
                        ],
                        'relationships' => [
                            'discussion' => [
                                'data' => [
                                    'type' => 'discussions',
                                    'id'   => '1',
                                ],
                            ],
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertIsNumeric($data['data']['id']);
        /** @var Discussion $discussion */
        $post = Discussion::find(1)->posts->firstWhere('id', $data['data']['id']);
        $this->assertNotNull($post);
        $this->assertEquals('predetermined content for automated testing - too-obscure', $post->content);
        $this->assertEquals('predetermined content for automated testing - too-obscure', Arr::get($data, 'data.attributes.content'));
        $this->assertTrue($post->is_approved);

        $flag = $post->flags->first();

        $this->assertNull($flag);
    }

    #[Test]
    #[DataProvider('unapprovedUsers')]
    public function unapprovedUsersDiscussionIsMarkedForApproval(?int $userId)
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => $userId,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'title'   => 'test - too-obscure',
                            'content' => 'predetermined content for automated testing - too-obscure',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertIsNumeric($data['data']['id']);
        /** @var Discussion $discussion */
        $discussion = Discussion::find($data['data']['id']);
        $this->assertNotNull($discussion);
        $this->assertEquals('test - too-obscure', $discussion->title);
        $this->assertEquals('test - too-obscure', Arr::get($data, 'data.attributes.title'));
        $this->assertFalse($discussion->is_approved);
        $this->assertFalse($discussion->posts->first()->is_approved);

        $post = $discussion->posts->first();

        $flag = $post->flags->first();

        $this->assertNotNull($flag);

        $this->assertEquals('approval', $flag->type);
    }

    #[Test]
    #[DataProvider('unapprovedUsers')]
    public function unapprovedUsersPostIsMarkedForApproval(?int $userId)
    {
        $response = $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => $userId,
                'json'            => [
                    'data' => [
                        'attributes' => [
                            'content' => 'predetermined content for automated testing - too-obscure',
                        ],
                        'relationships' => [
                            'discussion' => [
                                'data' => [
                                    'type' => 'discussions',
                                    'id'   => '1',
                                ],
                            ],
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getBody()->getContents(), true);

        $this->assertIsNumeric($data['data']['id']);

        $post = Discussion::find(1)->posts->firstWhere('id', $data['data']['id']);
        $this->assertNotNull($post);
        $this->assertEquals('predetermined content for automated testing - too-obscure', $post->content);
        $this->assertEquals('predetermined content for automated testing - too-obscure', Arr::get($data, 'data.attributes.content'));
        $this->assertFalse($post->is_approved);

        $flag = $post->flags->first();

        $this->assertNotNull($flag);

        $this->assertEquals('approval', $flag->type);
    }
}
