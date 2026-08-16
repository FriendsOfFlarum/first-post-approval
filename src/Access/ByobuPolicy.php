<?php

/*
 * This file is part of fof/first-post-approval.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\FirstPostApproval\Access;

use Flarum\User\Access\AbstractPolicy;
use Flarum\User\User;
use FoF\FirstPostApproval\Repository\FirstPostApprovalRepository;

/**
 * Prevents users who are still subject to first post approval from starting
 * private discussions.
 *
 * Byobu has no concept of approval, so a private discussion would otherwise be
 * published immediately and bypass the approval requirement entirely.
 *
 * This is done with a policy rather than by overriding the forum serializer
 * attributes, so that the restriction is enforced by the API as well as hidden
 * in the UI. Byobu checks these same abilities in its PersistRecipients
 * listener, and derives its `canStartPrivateDiscussion*` forum attributes from
 * them, so both layers are covered by this one policy.
 */
class ByobuPolicy extends AbstractPolicy
{
    /**
     * The Byobu abilities that should be denied while the user is subject to
     * first post approval.
     */
    public const RESTRICTED_ABILITIES = [
        'discussion.startPrivateDiscussionWithUsers',
        'discussion.startPrivateDiscussionWithGroups',
        'discussion.startPrivateDiscussionWithBlockers',
        'discussion.addMoreThanTwoUserRecipients',
    ];

    public function __construct(
        protected FirstPostApprovalRepository $firstPosts
    ) {
    }

    /**
     * @param mixed $instance
     *
     * @return string|void
     */
    public function can(User $actor, string $ability, $instance = null)
    {
        if (!in_array($ability, self::RESTRICTED_ABILITIES, true)) {
            return;
        }

        if (!$this->firstPosts->restrictsPrivateDiscussions()) {
            return;
        }

        if ($this->firstPosts->isUserSubjectToFPA($actor)) {
            return $this->forceDeny();
        }
    }
}
