<?php

namespace App\Filament\Pages;

use App\Models\KnowledgeBaseArticle;
use App\Models\Program;
use App\Models\KnowledgeBaseCategory;
use App\Models\KnowledgeBaseTag;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class KnowledgeBasePortal extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Knowledge Base';
    protected static ?string $title = 'Knowledge Base View';
    protected static string $view = 'filament.knowledgebase.knowledge-base-portal';

    public $search = '';
    public $program = null;
    public $category = null;
    public $tag = null;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public function mount()
    {
        $this->search = request('search', '');
        $this->program = request('program', null);
        $this->category = request('category', null);
        $this->tag = request('tag', null);
    }


    public function getProgramsProperty()
    {
        return Program::orderBy('name')->get();
    }

    public function getCategoriesProperty()
    {
        return KnowledgeBaseCategory::orderBy('name')->get();
    }

    public function getTagsProperty()
    {
        return KnowledgeBaseTag::orderBy('name')->get();
    }

    public function getArticlesProperty()
    {
        $query = KnowledgeBaseArticle::query()
            ->with(['programs', 'category', 'tags', 'attachments'])
            ->where('is_published', true);

        if ($this->search) {
            $query->where(function (Builder $q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('content', 'like', "%{$this->search}%");
            });
        }
        if ($this->program) {
            $query->whereHas('programs', function ($q) {
                $q->where('programs.id', $this->program);
            });
        }
        if ($this->category) {
            $query->where('category_id', $this->category);
        }
        if ($this->tag) {
            $query->whereHas('tags', function ($q) {
                $q->where('knowledge_base_tags.id', $this->tag);
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate(12);
    }
}
