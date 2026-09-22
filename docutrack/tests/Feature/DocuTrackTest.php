<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FoundDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocuTrackTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_public_pages_render(): void
    {
        foreach (['/', '/login', '/register', '/feedback', '/lang/fr'] as $url) {
            $this->get($url)->assertStatus($url === '/lang/fr' ? 302 : 200);
        }
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_register_and_login(): void
    {
        $this->post('/register', ['name' => 'Ada', 'email' => 'ada@x.cm', 'phone' => '+237699', 'password' => 'secret12'])
            ->assertRedirect('/dashboard');
        $this->post('/logout');
        $this->post('/login', ['email' => 'ada@x.cm', 'password' => 'secret12'])->assertRedirect('/dashboard');
    }

    public function test_declaration_is_notified_when_matching_document_is_reported_and_paid(): void
    {
        $owner = User::where('email', 'john@example.com')->first();
        $finder = User::factory()->create();
        $cni = Category::first();

        $this->actingAs($owner)->post('/declare', [
            'category_id' => $cni->id, 'full_name' => 'Paul Biya Junior', 'last_location' => 'Douala',
        ])->assertRedirect('/notifications');

        $this->actingAs($finder)->post('/found', [
            'category_id' => $cni->id, 'owner_name' => 'paul biya', 'location' => 'Douala',
            'deposit_point' => 'Commissariat 1er', 'image' => 'data:image/png;base64,'.base64_encode('png'),
        ])->assertRedirect('/finder');

        $doc = FoundDocument::where('owner_name', 'paul biya')->firstOrFail();
        $this->assertDatabaseHas('alerts', ['user_id' => $owner->id, 'found_document_id' => $doc->id]);

        $this->actingAs($owner)->get('/search?category_id='.$cni->id.'&owner_name=Paul')->assertSee('paul biya');
        $this->get("/documents/{$doc->id}")->assertForbidden();
        $this->post("/documents/{$doc->id}/pay", ['method' => 'Orange Money', 'account_number' => '699000000'])
            ->assertRedirect("/documents/{$doc->id}");
        $this->get("/documents/{$doc->id}")->assertOk()->assertSee('Commissariat 1er');
    }

    public function test_authenticated_pages_render_in_french(): void
    {
        $this->actingAs(User::first())->withSession(['locale' => 'fr']);
        foreach (['/dashboard', '/finder', '/owner', '/found/create', '/search', '/declare', '/notifications'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/search?category_id=1&owner_name=Nobody')->assertSee('Aucun', false);
    }

    public function test_admin_area_is_restricted(): void
    {
        $this->actingAs(User::where('role', 'user')->first())->get('/admin')->assertForbidden();

        $admin = User::where('role', 'admin')->first();
        foreach (['documents', 'users', 'categories', 'feedback'] as $tab) {
            $this->actingAs($admin)->get("/admin?tab=$tab")->assertOk();
        }
        $this->post('/admin/categories', ['name' => 'Carte de séjour'])->assertRedirect();
        $this->assertDatabaseHas('categories', ['name' => 'Carte de séjour']);
    }
}
