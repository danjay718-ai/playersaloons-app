<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only published/display content; account identities and financial
        // system accounts must keep their existing identifiers.
        $content = [
            'landing_sections' => ['title', 'subtitle', 'body', 'cta_label'],
            'landing_section_items' => ['title', 'subtitle', 'body', 'label'],
            'policy_pages' => ['title', 'summary', 'content'],
            'cms_page_translations' => ['title', 'excerpt', 'content'],
            'game_translations' => ['description', 'rules'],
            'game_head_to_head_defaults' => ['description', 'rules'],
            'tournament_templates' => ['name', 'description', 'rules'],
            'tournaments' => ['name', 'description', 'rules'],
            'system_settings' => ['value'],
        ];

        foreach ($content as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            if ($columns === []) {
                continue;
            }
            $query = DB::table($table)->select(['id', ...$columns]);
            if ($table === 'system_settings') {
                $query->where('key', 'like', 'about.%');
            }
            $query->orderBy('id')->chunkById(100, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $changes = [];
                    foreach ($columns as $column) {
                        if (! is_string($row->$column)) {
                            continue;
                        }
                        $value = preg_replace_callback('/playersaloons/i', static function ($match): string {
                            return match ($match[0]) {
                                'PLAYERSALOONS' => 'GAMERSRIVAL',
                                'playersaloons' => 'gamersrival',
                                default => 'GamersRival',
                            };
                        }, $row->$column);
                        if ($value !== $row->$column) {
                            $changes[$column] = $value;
                        }
                    }
                    if ($changes !== []) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Branding copy is retained: a blanket reversal would also change
        // legitimate GamersRival content authored after this migration.
    }
};
