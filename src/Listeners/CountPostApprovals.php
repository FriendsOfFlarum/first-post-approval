<?php

/*
 * This file is part of fof/first-post-approval.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\FirstPostApproval\Listeners;

use Flarum\Approval\Event\PostWasApproved;

class CountPostApprovals
{
    public function handle(PostWasApproved $event): void
    {
        $user = $event->post->user;

        if (!$user) {
            return;
        }

        // Do not count posts if they were hidden (which approves them)
        if ($event->post->hidden_at) {
            return;
        }

        if ($event->post->number === 1) {
            $user->first_discussion_approval_count++;
        } else {
            $user->first_post_approval_count++;
        }

        $user->save();
    }
}
