<?php

namespace App\Services;

use App\Models\Team;

class TeamContext
{
    protected ?Team $team = null;

    public function set(Team $team): void
    {
        $this->team = $team;
    }

    public function get(): ?Team
    {
        return $this->team;
    }

    public function id(): ?int
    {
        return $this->team?->id;
    }

    public function require(): Team
    {
        return $this->team ?? throw new \RuntimeException('No team context set.');
    }
}
