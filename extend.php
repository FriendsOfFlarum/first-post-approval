<?php

/*
 * This file is part of fof/first-post-approval.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\FirstPostApproval;

use Flarum\Approval\Event\PostWasApproved;
use Flarum\Extend;
use Flarum\Post\Event\Saving;
use Flarum\User\User;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\Model(User::class))
        ->cast('first_post_approval_count', 'int')
        ->cast('first_discussion_approval_count', 'int'),

    (new Extend\Event())
        ->listen(PostWasApproved::class, Listeners\CountPostApprovals::class)
        ->listen(Saving::class, Listeners\UnapproveNewPosts::class),

    (new Extend\Settings())
        ->default('fof-first-post-approval.discussionCount', 1)
        ->default('fof-first-post-approval.postCount', 1)
        ->default('fof-first-post-approval.restrictPrivateDiscussions', true),

    (new Extend\Conditional())
        ->whenExtensionEnabled('fof-byobu', fn () => [
            (new Extend\Policy())
                ->globalPolicy(Access\ByobuPolicy::class),
        ]),
];
