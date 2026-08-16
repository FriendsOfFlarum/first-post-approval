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

use FoF\FirstPostApproval\Repository\FirstPostApprovalRepository;
use Flarum\Discussion\Discussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Post\Event\Saving;
use Flarum\Settings\SettingsRepositoryInterface;

class UnapproveNewPosts
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected FirstPostApprovalRepository $firstPosts,
        protected ExtensionManager $extensions
    ) {
    }

    public function handle(Saving $event): void
    {
        $post = $event->post;

        if ($post->exists || $event->actor->can('firstPostWithoutApproval', $post->discussion) || $this->isPrivate($post->discussion)) {
            return;
        }

        $discussionCount = $this->firstPosts->requiredDiscussionCount();

        if ($post->discussion->first_post_id === null && $discussionCount) {
            // If this is a new discussion and if a rule has been defined for new discussions
            if ($event->actor->first_discussion_approval_count >= $discussionCount) {
                return;
            }
        } else {
            // If this is a reply, or if there's no rule defined for new discussions
            if (($event->actor->first_discussion_approval_count + $event->actor->first_post_approval_count) >= $this->firstPosts->requiredPostCount()) {
                return;
            }
        }

        $post->is_approved = false;

        $this->firstPosts->flagPost($post);
    }

    protected function isPrivate(Discussion $discussion): bool
    {
        if ($this->extensions->isEnabled('fof-byobu')) {
            /** @var \FoF\Byobu\Discussion\Screener $byobu */
            $byobu = resolve(\FoF\Byobu\Discussion\Screener::class);
            return $byobu->fromDiscussion($discussion)->isPrivate();
        }

        return false;
    }
}
