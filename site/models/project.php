<?php

use Kirby\Cms\Page;
use Kirby\Cms\PagePermissions;

class ContributeurLockedPermissions extends PagePermissions
{
    public function can(string $action): bool
    {
        $allowed = ['read', 'access', 'list', 'preview'];
        if (in_array($action, $allowed)) {
            return parent::can($action);
        }
        return false;
    }
}

class ProjectPage extends Page
{
    public function isEditableByContributeur(): bool
    {
        $user = kirby()->user();
        if (!$user || $user->role()->name() !== 'contributeur') {
            return true;
        }

        if ($this->status() !== 'draft') {
            return false;
        }

        $createdBy = $this->content()->get('createdBy')->value();
        if (!empty($createdBy) && $createdBy !== $user->email()) {
            return false;
        }

        return true;
    }

    public function permissions(): PagePermissions
    {
        $user = kirby()->user();

        if ($user && $user->role()->name() === 'contributeur' && !$this->isEditableByContributeur()) {
            return new ContributeurLockedPermissions($this);
        }

        return parent::permissions();
    }
}
