<?php

namespace App\Filament\Pages;

use App\Models\KnowledgeBaseArticle;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class KnowledgeBaseArticleDetail extends Page
{
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationLabel = null;
    protected static ?string $title = 'Article Details';
    protected static string $view = 'filament.knowledgebase.knowledge-base-article-detail';

    public KnowledgeBaseArticle $article;
    // Hide from sidebar navigation
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(Request $request, $record)
    {
        $this->article = KnowledgeBaseArticle::with(['programs', 'category', 'tags', 'attachments'])->findOrFail($record);
    }

    public function getViewData(): array
    {
        return [
            'article' => $this->article,
        ];
    }

    public static function getRouteName(?string $panel = null): string
    {
        return 'knowledge-base-article-detail';
    }


    public static function getRoute(): string
    {
        return static::getSlug() . '/{record}';
    }
}
