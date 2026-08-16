<?php

/*
 * This file is part of fof/first-post-approval.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\FirstPostApproval\Repository;

use Carbon\Carbon;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

class FirstPostApprovalRepository
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function flagPost(Post $post): void
    {
        $post->afterSave(function (Post $post) {
            if ($post->number === 1) {
                $post->discussion->is_approved = false;
                $post->discussion->save();
            }

            $flag = new Flag();

            $flag->post_id = $post->id;
            $flag->type = 'approval';
            $flag->created_at = Carbon::now();

            $flag->save();
        });
    }

    public function isUserSubjectToFPA(User $user): bool
    {
        // If user has bypass permission, then early return
        if ($user->can('firstPostWithoutApproval')) {
            return false;
        }

        if ($user->comment_count < $this->requiredPostCount() || $user->discussion_count < $this->requiredDiscussionCount()) {
            return true;
        }

        return false;
    }

    public function requiredDiscussionCount(): int
    {
        return (int) $this->settings->get('fof-first-post-approval.discussionCount');
    }

    public function requiredPostCount(): int
    {
        return (int) $this->settings->get('fof-first-post-approval.postCount');
    }

    /**
     * Whether users subject to first post approval are prevented from starting
     * private discussions.
     */
    public function restrictsPrivateDiscussions(): bool
    {
        return (bool) $this->settings->get('fof-first-post-approval.restrictPrivateDiscussions');
    }
}
