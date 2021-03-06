<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\ArticleForm;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ArticleFormTest extends TestCase
{

    use RefreshDatabase;


    /** @test */
    public function guests_cannot_create_or_update_articles()
    {
        $this->get(route('articles.create'))
            ->assertRedirect('login');


        $article = Article::factory()->create();

        $this->get(route('articles.edit',$article))
            ->assertRedirect('login');

    }

    /** @test */
    public function article_form_render_properly()
    {

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('articles.create'))
            ->assertSeeLivewire(ArticleForm::class);


        $article = Article::factory()->create();

        $this->actingAs($user)->get(route('articles.edit',$article))
            ->assertSeeLivewire(ArticleForm::class);

    }

    /** @test */
    public function can_create_new_articles()
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('post-image.png');

        $user = User::factory()->create();

        Livewire::actingAs($user)->test(ArticleForm::class)
            ->set('image',$image)
            ->set('article.title', 'New article')
            ->set('article.slug', 'new-article')
            ->set('article.content', 'Content article')
            ->call('save')
            ->assertSessionHas('status')
            ->assertRedirect(route('articles.index'));

        $this->assertDatabaseHas('articles', [
            'image'   => Storage::disk('public')->files()[0],
            'title'   => 'New article',
            'slug'    => 'new-article',
            'content' => 'Content article',
            'user_id' =>  $user->id
        ]);
    }


    /** @test */
    public function can_update_articles()
    {
        $article = Article::factory()->create();

        Livewire::actingAs($article->user)->test(ArticleForm::class,['article' => $article])
            ->assertSet('article.title',$article->title)
            ->assertSet('article.slug',$article->slug)
            ->assertSet('article.content',$article->content)
            ->set('article.title','Title updated')
            ->set('article.slug','title-updated')
            ->set('article.content','Content updated')
            ->call('save')
            ->assertSessionHas('status')
            ->assertRedirect(route('articles.index'));

        $this->assertDatabaseCount('articles',1);

        $this->assertDatabaseHas('articles', [
            'title'   => "Title updated",
            'slug'    => "title-updated",
            'content' => "Content updated",
            'user_id' => $article->user->id
        ]);
    }

    /** @test */
    public function can_update_articles_with_image()
    {

        Storage::fake('public');

        $oldImage = UploadedFile::fake()->image('old-image.png');
        $oldPathImage = $oldImage->store('/','public');
        $newImage = UploadedFile::fake()->image('new-image.png');

        $article = Article::factory()->create([
            'image' => $oldPathImage
        ]);

        Livewire::actingAs($article->user)->test(ArticleForm::class,['article' => $article])
            ->set('image',$newImage)
            ->call('save')
            ->assertSessionHas('status')
            ->assertRedirect(route('articles.index'));

        Storage::disk('public')->exists($article->fresh()->image);
        Storage::disk('public')->assertMissing($oldPathImage);
    }

    /** @test */
    public function title_is_required()
    {
        Livewire::test(ArticleForm::class)
            ->set('article.content', "Content for the article")
            ->call("save")
            ->assertHasErrors(['article.title' =>'required'])
            ;
    }

    /** @test */
    public function slug_is_required()
    {
        Livewire::test(ArticleForm::class)
            ->set('article.title',"Title for article")
            ->set('article.slug',null)
            ->set('article.content', "Content for the article")
            ->call("save")
            ->assertHasErrors(['article.slug' =>'required'])
        ;
    }

    /** @test */
    public function slug_must_only_contain_letters_numbers_dashes_and_underscores()
    {
        Livewire::test(ArticleForm::class)
            ->set('article.title',"Title for article")
            ->set('article.slug',"#$#%$#%#")
            ->set('article.content', "Content for the article")
            ->call("save")
            ->assertHasErrors(['article.slug' =>'alpha_dash'])
        ;
    }

    /** @test */
    public function slug_is_unique()
    {

        $article = Article::factory()->create();

        Livewire::test(ArticleForm::class)
            ->set('article.title',"Title for article")
            ->set('article.slug',$article->slug)
            ->set('article.content', "Content for the article")
            ->call("save")
            ->assertHasErrors(['article.slug' =>'unique'])
        ;
    }

    /** @test */
    public function unique_rule_should_be_ignored_when_updating_the_same_slug()
    {

        $article = Article::factory()->create();

        Livewire::test(ArticleForm::class)
            ->set('article.title',"Title for article")
            ->set('article.slug',$article->slug)
            ->set('article.content', "Content for the article")
            ->call("save")
            ->assertHasErrors(['article.slug' =>'unique'])
        ;
    }

    /** @test */
    public function title_must_be_4_characters_min()
    {
        Livewire::test(ArticleForm::class)
            ->set("article.title","AR")
            ->set('article.content', "Content for the article")
            ->call("save")
            ->assertHasErrors(['article.title' =>'min'])
        ;
    }

    /** @test */
    public function content_is_required()
    {
        Livewire::test(ArticleForm::class)
            ->set("article.title","Title for new article")
            ->call("save")
            ->assertHasErrors(['article.content' =>'required'])
        ;
    }

    /** @test */
    public function real_time_validations_works_for_title()
    {
        Livewire::test(ArticleForm::class)
            ->set("article.title","")
            ->assertHasErrors(['article.title' =>'required'])
            ->set("article.title","AR")
            ->assertHasErrors(['article.title' =>'min'])
            ->set("article.title","Title for the new article")
            ->assertHasNoErrors(['article.title' =>'required'])
        ;
    }

    /** @test */
    public function real_time_validations_works_for_content()
    {
        Livewire::test(ArticleForm::class)
            ->set("article.content","")
            ->assertHasErrors(['article.content' =>'required'])
            ->set("article.content","Body for the new article")
            ->assertHasNoErrors(['article.content' =>'required'])
        ;
    }

    /** @test */
    public function slug_is_generated_automatically()
    {
        Livewire::test(ArticleForm::class)
            ->set("article.title","Title for article")
            ->assertSet('article.slug',"title-for-article")
        ;
    }


}
