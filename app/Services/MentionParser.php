<?php

namespace App\Services;

use App\Models\Agent;

class MentionParser
{
    /**
     * Parse @mentions from content and resolve agent IDs within team scope.
     *
     * @return array{agent_ids: string[], agent_names: string[]}
     */
    public function parse(string $content): array
    {
        preg_match_all('/@([\w-]+)/', $content, $matches);

        $names = array_unique($matches[1]);

        if (empty($names)) {
            return ['agent_ids' => [], 'agent_names' => []];
        }

        $agents = Agent::whereIn('name', $names)->get();

        return [
            'agent_ids' => $agents->pluck('id')->values()->all(),
            'agent_names' => $agents->pluck('name')->values()->all(),
        ];
    }
}
