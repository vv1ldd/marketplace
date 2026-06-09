<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Seller;
use App\Models\SovereignLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);
    }

    private function agreementSigningContext(User $user, LegalEntity $entity, string $nonce = 'agreement-nonce'): array
    {
        $context = [
            'action' => 'agreement.sign',
            'legal_entity_id' => $entity->id,
            'expected_status' => 'pending_signature',
            'user_id' => $user->id,
            'entity_l1_address' => strtolower((string) $user->sovereignIdentityAddress()),
            'inn_hash' => hash('sha256', (string) $entity->inn),
            'legal_name_hash' => hash('sha256', mb_strtolower(trim((string) $entity->name))),
            'agreement_id' => null,
            'agreement_type' => 'b2b',
            'agreement_hash' => hash('sha256', 'offer-body'),
            'agreement_published_at' => null,
            'signer_role' => 'ceo',
            'signer_name_hash' => hash('sha256', mb_strtolower('offer signer')),
            'nonce' => $nonce,
        ];
        $context['context_hash'] = hash('sha256', json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $context;
    }

    public function test_public_login_page_is_passkey_only(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Продолжить через Meanly One')
            ->assertDontSee('Войти с помощью Passkey')
            ->assertDontSee('Войти по паролю');
    }

    public function test_logout_routes_return_to_home(): void
    {
        Role::firstOrCreate(['name' => User::ROLE_WALLET_HOLDER, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $partner = User::factory()->create();
        $partner->assignRole(User::ROLE_MERCHANT_NODE);
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $partner->id,
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->actingAs($user)
            ->post(route('cabinet.logout'))
            ->assertRedirect(route('home'));

        $this->actingAs($partner)
            ->post(route('partner.logout'))
            ->assertRedirect(route('home'));

        $this->assertGreaterThanOrEqual(2, SovereignLedger::where('event_type', 'AUTH_LOGOUT_INTENT')->where('entity_id', $user->id)->count());
        $this->assertGreaterThanOrEqual(1, SovereignLedger::where('event_type', 'AUTH_LOGOUT_INTENT')->where('entity_id', $partner->id)->count());
    }

    public function test_json_logout_returns_success_without_legacy_redirect(): void
    {
        $user = User::factory()->create();
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('logout', [], false))
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertHeaderMissing('Location');

        $this->assertGuest();
    }

    public function test_passkey_delete_records_remove_intent(): void
    {
        $user = User::factory()->create();
        $passkey = \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user);

        (new \App\Livewire\PasskeysComponent())->deletePasskey($passkey->id);

        $event = SovereignLedger::where('event_type', 'PASSKEY_REMOVE_INTENT')->firstOrFail();

        $this->assertSame('passkey.remove', data_get($event->payload, 'intent_type'));
        $this->assertSame(hash('sha256', (string) $passkey->credential_id), data_get($event->payload, 'credential_id_hash'));
        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_merchant_registration_assigns_seller_role_with_sellers_guard(): void
    {
        Role::firstOrCreate(['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $seller = Seller::create([
            'first_name' => 'Merchant',
            'is_active' => true,
        ]);

        $controller = new \App\Http\Controllers\PartnerRegistrationController();
        $method = new \ReflectionMethod($controller, 'assignMerchantNodeRoles');
        $method->setAccessible(true);
        $method->invoke($controller, $user, $seller);

        $this->assertTrue($user->fresh()->hasRole(User::ROLE_MERCHANT_NODE));
        $this->assertDatabaseHas('roles', ['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'sellers']);
        $this->assertTrue($seller->fresh()->hasRole(User::ROLE_MERCHANT_NODE, 'sellers'));
    }

    public function test_pending_moderation_partner_sees_onboarding_instead_of_dashboard(): void
    {
        $user = User::factory()->create();
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Pending Business LLC',
            'inn' => '770000000001',
            'email' => 'company@example.test',
            'status' => 'pending_moderation',
            'is_active' => false,
            'agreement_signed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('partner.dashboard'))
            ->assertRedirect(route('partner.onboarding'));

        $this->actingAs($user)
            ->get(route('partner.onboarding'))
            ->assertOk()
            ->assertSee('Мы проверяем компанию')
            ->assertSee('Все в порядке, сейчас ничего делать не нужно')
            ->assertSee('подтвержденный email компании')
            ->assertSee('company@example.test')
            ->assertSee('Заявка отправлена')
            ->assertSee('проверяем компанию')
            ->assertSee('Pending Business LLC')
            ->assertDontSee('Meanly Support')
            ->assertDontSee('Напишите, пожалуйста');
    }

    public function test_partner_deposit_mutation_endpoints_are_disabled(): void
    {
        Role::firstOrCreate(['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'entity_l1_address' => 'sl1e_'.str_repeat('c', 39),
            'meta' => ['simple_l1' => ['identity_rule' => 'external_identity_provider']],
        ]);
        $user->assignRole(User::ROLE_MERCHANT_NODE);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Active Business LLC',
            'inn' => '770000000009',
            'status' => 'active',
            'is_active' => true,
            'available_balance' => 100,
            'balance' => 100,
        ]);
        $user->managedLegalEntities()->attach($entity->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->postJson(route('partner.dashboard.finance.deposit'), ['amount' => 1000])
            ->assertStatus(410);

        $this->actingAs($user)
            ->postJson(route('partner.dashboard.deposit_intent'), ['amount' => 1000])
            ->assertStatus(410);

        $this->actingAs($user)
            ->postJson(route('partner.dashboard.clear_deposit_intent'), ['token' => 'DEP-TEST'])
            ->assertStatus(410);

        $entity->refresh();
        $this->assertSame(100.0, (float) $entity->available_balance);
        $this->assertSame(100.0, (float) $entity->balance);
    }

    public function test_partner_finance_mutations_require_privileged_role(): void
    {
        Role::firstOrCreate(['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'entity_l1_address' => 'sl1e_'.str_repeat('d', 39),
            'meta' => ['simple_l1' => ['identity_rule' => 'external_identity_provider']],
        ]);
        $user->assignRole(User::ROLE_MERCHANT_NODE);

        $entity = LegalEntity::create([
            'name' => 'Viewer Business LLC',
            'inn' => '770000000010',
            'status' => 'active',
            'is_active' => true,
            'available_balance' => 100,
            'balance' => 100,
        ]);
        $user->managedLegalEntities()->attach($entity->id, ['role' => 'viewer']);

        $this->actingAs($user)
            ->postJson(route('partner.dashboard.finance.deposit'), ['amount' => 1000])
            ->assertStatus(410);

        $this->assertSame(100.0, (float) $entity->refresh()->available_balance);
    }

    public function test_business_registration_validates_representative_full_name(): void
    {
        $user = User::factory()->create();
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->mock(\App\Services\DaDataService::class, function ($mock): void {
            $mock->shouldReceive('findByInn')->andReturn([
                'type' => 'LEGAL',
                'inn' => '7700000000',
                'name' => ['short_with_opf' => 'ООО Ромашка'],
                'ogrn' => '1234567890123',
                'address' => ['value' => 'Москва'],
                'management' => ['name' => 'Иванов Иван Иванович'],
            ]);
        });

        $this->actingAs($user)
            ->withSession(['business_registration_verified_email' => 'company@example.test'])
            ->post(route('business.register.submit'), [
                'inn' => '7700000000',
                'jurisdiction' => 'RU',
                'signer_role' => 'representative',
                'signer_name' => 'Иван',
                'business_email' => 'company@example.test',
            ])
            ->assertSessionHasErrors('signer_name');
    }

    public function test_business_registration_validates_ip_full_name_from_dadata(): void
    {
        $user = User::factory()->create();
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->mock(\App\Services\DaDataService::class, function ($mock): void {
            $mock->shouldReceive('findByInn')->andReturn([
                'type' => 'INDIVIDUAL',
                'inn' => '526216895584',
                'fio' => [
                    'surname' => 'Иванов1',
                    'name' => 'Иван',
                    'patronymic' => 'Иванович',
                ],
                'ogrnip' => '325527500033290',
                'address' => ['value' => 'Нижний Новгород'],
            ]);
        });

        $this->actingAs($user)
            ->withSession(['business_registration_verified_email' => 'ip@example.test'])
            ->post(route('business.register.submit'), [
                'inn' => '526216895584',
                'jurisdiction' => 'RU',
                'signer_role' => 'ceo',
                'address' => 'Нижний Новгород',
                'business_email' => 'ip@example.test',
            ])
            ->assertSessionHasErrors('director_name');
    }

    public function test_business_registration_has_dedicated_simple_l1_target(): void
    {
        $this->get('/business/register')
            ->assertOk()
            ->assertSee('Регистрация бизнеса')
            ->assertSee('Сначала подтвердим рабочий email')
            ->assertSee('Рабочий email')
            ->assertSee('Получить код')
            ->assertSee('ИНН организации')
            ->assertSee('Найдена организация')
            ->assertSee('Meanly One')
            ->assertDontSee('Имя владельца профиля')
            ->assertDontSee('Телефон для связи')
            ->assertSee('name="registration_target"', false)
            ->assertSee('value="legal_entity"', false);
    }

    public function test_business_registration_with_sl1_identity_does_not_require_local_passkey_attestation(): void
    {
        Role::firstOrCreate(['name' => User::ROLE_WALLET_HOLDER, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'meta' => [
                'simple_l1' => [
                    'identity_rule' => 'external_identity_provider',
                ],
            ],
        ]);

        $this->mock(\App\Services\DaDataService::class, function ($mock): void {
            $mock->shouldReceive('findByInn')->andReturn([
                'type' => 'LEGAL',
                'inn' => '7700000000',
                'name' => ['short_with_opf' => 'ООО Ромашка'],
                'ogrn' => '1234567890123',
                'kpp' => '770001001',
                'address' => ['value' => 'Москва'],
                'management' => ['name' => 'Иванов Иван Иванович'],
            ]);
        });

        $this->actingAs($user)
            ->withSession(['business_registration_verified_email' => 'company@example.test'])
            ->post(route('business.register.submit'), [
                'inn' => '7700000000',
                'jurisdiction' => 'RU',
                'signer_role' => 'ceo',
                'business_email' => 'company@example.test',
            ])
            ->assertRedirect(route('partner.register.offer'));

        $entity = \App\Models\LegalEntity::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('pending_signature', $entity->status);
        $this->assertSame($user->sovereignIdentityAddress(), data_get($entity->agreement_metadata, 'l1_address'));
        $this->assertNull($entity->agreement_signature);
    }

    public function test_offer_signature_uses_sl1e_redirect_without_local_passkey_or_qr(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        $keyAddress = 'sl1_'.str_repeat('b', 40);

        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => $keyAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => [
                'simple_l1' => [
                    'identity_rule' => 'external_identity_provider',
                ],
            ],
        ]);

        $this->actingAs($user)
            ->withSession([
                'partner_registration' => [
                    'is_b2b' => true,
                    'legal_name' => 'Offer Test LLC',
                    'signer_role' => 'ceo',
                    'signer_name' => 'Offer Signer',
                    'business_email' => 'offer@example.test',
                ],
            ])
            ->get(route('partner.register.offer'))
            ->assertOk()
            ->assertSee("window.location.search).get('sl1e_offer_complete')", false)
            ->assertSee('Переходим в Meanly One')
            ->assertSee('intent_type=agreement.sign', false)
            ->assertSee(rawurlencode('Подписать публичную оферту'), false)
            ->assertSee(rawurlencode('Подтвердить подпись'), false)
            ->assertDontSee(rawurlencode('proof token'), false)
            ->assertDontSee(rawurlencode('wallet key'), false)
            ->assertDontSee('@simplewebauthn/browser')
            ->assertDontSee('startAuthentication')
            ->assertDontSee('api.qrserver.com')
            ->assertDontSee('Подписание через FaceID');
    }

    public function test_offer_signature_accepts_matching_sl1e_proof(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        $keyAddress = 'sl1_'.str_repeat('b', 40);

        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => $keyAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => [
                'simple_l1' => [
                    'identity_rule' => 'external_identity_provider',
                ],
            ],
        ]);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Offer Test LLC',
            'inn' => '1234567890',
            'email' => 'offer@example.test',
            'status' => 'pending_signature',
            'is_active' => false,
            'agreement_metadata' => [],
        ]);
        $user->managedLegalEntities()->attach($entity->id, ['role' => 'admin']);
        $context = $this->agreementSigningContext($user, $entity);
        $resource = 'agreement_context:'.$context['context_hash'];

        $this->actingAs($user)
            ->withSession([
                'partner_registration' => [
                    'is_b2b' => true,
                    'legal_name' => 'Offer Test LLC',
                    'signer_role' => 'ceo',
                    'signer_name' => 'Offer Signer',
                    'business_email' => 'offer@example.test',
                ],
                'simple_l1_identity' => [
                    'entity_l1_address' => $entityAddress,
                    'key_l1_address' => $keyAddress,
                    'proof_token_hash' => hash('sha256', 'proof-token'),
                    'proof' => [
                        'intent' => [
                            'type' => 'agreement.sign',
                            'nonce' => 'agreement-nonce',
                            'resource' => $resource,
                        ],
                    ],
                ],
                'agreement_signing_entity_l1_address' => $entityAddress,
                'agreement_signing_nonce' => 'agreement-nonce',
                'agreement_signing_resource' => $resource,
                'agreement_signing_context' => $context,
                'agreement_signing_started_at' => now()->toIso8601String(),
            ])
            ->postJson(route('partner.register.agreement.sign'), ['simple_l1_sign' => true])
            ->assertOk()
            ->assertJson(['success' => true]);

        $entity->refresh();
        $this->assertSame('pending_moderation', $entity->status);
        $this->assertSame('simple_l1_proof_v1', data_get($entity->agreement_metadata, 'signature_type'));
        $this->assertSame($keyAddress, data_get($entity->agreement_metadata, 'key_l1_address'));
        $this->assertNotNull($entity->agreement_signed_at);
        $this->assertStringContainsString('simple_l1_proof_v1', $entity->agreement_signature);
    }

    public function test_offer_completion_does_not_rotate_signing_context_before_finalize(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        $keyAddress = 'sl1_'.str_repeat('b', 40);

        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => $keyAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => ['simple_l1' => ['identity_rule' => 'external_identity_provider']],
        ]);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Offer Test LLC',
            'inn' => '1234567890',
            'email' => 'offer@example.test',
            'status' => 'pending_signature',
            'is_active' => false,
            'agreement_metadata' => [],
        ]);
        $user->managedLegalEntities()->attach($entity->id, ['role' => 'admin']);

        $context = $this->agreementSigningContext($user, $entity);
        $resource = 'agreement_context:'.$context['context_hash'];
        $other = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Newer Pending LLC',
            'inn' => '1234567891',
            'email' => 'newer@example.test',
            'status' => 'pending_signature',
            'is_active' => false,
            'agreement_metadata' => [],
        ]);
        $user->managedLegalEntities()->attach($other->id, ['role' => 'admin']);

        $this->actingAs($user)
            ->withSession([
                'partner_registration' => [
                    'is_b2b' => true,
                    'legal_name' => 'Offer Test LLC',
                    'signer_role' => 'ceo',
                    'signer_name' => 'Offer Signer',
                    'business_email' => 'offer@example.test',
                ],
                'agreement_signing_entity_l1_address' => $entityAddress,
                'agreement_signing_nonce' => 'agreement-nonce',
                'agreement_signing_resource' => $resource,
                'agreement_signing_context' => $context,
                'agreement_signing_started_at' => now()->toIso8601String(),
            ])
            ->get(route('partner.register.offer', ['sl1e_offer_complete' => 1]))
            ->assertOk()
            ->assertSessionHas('agreement_signing_nonce', 'agreement-nonce')
            ->assertSessionHas('agreement_signing_resource', $resource);
    }

    public function test_offer_signature_does_not_apply_context_to_another_pending_entity(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        $keyAddress = 'sl1_'.str_repeat('b', 40);

        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => $keyAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => ['simple_l1' => ['identity_rule' => 'external_identity_provider']],
        ]);

        $signedFor = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Signed For LLC',
            'inn' => '1234567890',
            'email' => 'one@example.test',
            'status' => 'pending_signature',
            'is_active' => false,
            'agreement_metadata' => [],
        ]);
        $other = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Other Pending LLC',
            'inn' => '1234567891',
            'email' => 'two@example.test',
            'status' => 'pending_signature',
            'is_active' => false,
            'agreement_metadata' => [],
        ]);
        $user->managedLegalEntities()->attach($signedFor->id, ['role' => 'admin']);
        $user->managedLegalEntities()->attach($other->id, ['role' => 'admin']);

        $context = $this->agreementSigningContext($user, $signedFor);
        $resource = 'agreement_context:'.$context['context_hash'];

        $this->actingAs($user)
            ->withSession([
                'partner_registration' => [
                    'is_b2b' => true,
                    'legal_name' => 'Signed For LLC',
                    'signer_role' => 'ceo',
                    'signer_name' => 'Offer Signer',
                    'business_email' => 'one@example.test',
                ],
                'simple_l1_identity' => [
                    'entity_l1_address' => $entityAddress,
                    'key_l1_address' => $keyAddress,
                    'proof_token_hash' => hash('sha256', 'proof-token'),
                    'proof' => [
                        'intent' => [
                            'type' => 'agreement.sign',
                            'nonce' => 'agreement-nonce',
                            'resource' => $resource,
                        ],
                    ],
                ],
                'agreement_signing_entity_l1_address' => $entityAddress,
                'agreement_signing_nonce' => 'agreement-nonce',
                'agreement_signing_resource' => $resource,
                'agreement_signing_context' => $context,
                'agreement_signing_started_at' => now()->toIso8601String(),
            ])
            ->postJson(route('partner.register.agreement.sign'), ['simple_l1_sign' => true])
            ->assertOk();

        $this->assertSame('pending_moderation', $signedFor->refresh()->status);
        $this->assertSame('pending_signature', $other->refresh()->status);
        $this->assertNull($other->agreement_signature);
    }

    public function test_offer_signature_recovers_context_from_sl1e_intent_when_session_rotated(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        $keyAddress = 'sl1_'.str_repeat('b', 40);

        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => $keyAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => ['simple_l1' => ['identity_rule' => 'external_identity_provider']],
        ]);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Offer Test LLC',
            'inn' => '1234567890',
            'email' => 'offer@example.test',
            'status' => 'pending_signature',
            'is_active' => false,
            'agreement_metadata' => [],
        ]);
        $user->managedLegalEntities()->attach($entity->id, ['role' => 'admin']);

        $signedContext = $this->agreementSigningContext($user, $entity, 'signed-nonce');
        $signedResource = 'agreement_context:'.$signedContext['context_hash'];
        $rotatedContext = $this->agreementSigningContext($user, $entity, 'rotated-nonce');
        $rotatedResource = 'agreement_context:'.$rotatedContext['context_hash'];

        $this->actingAs($user)
            ->withSession([
                'partner_registration' => [
                    'is_b2b' => true,
                    'legal_name' => 'Offer Test LLC',
                    'signer_role' => 'ceo',
                    'signer_name' => 'Offer Signer',
                    'business_email' => 'offer@example.test',
                ],
                'simple_l1_identity' => [
                    'entity_l1_address' => $entityAddress,
                    'key_l1_address' => $keyAddress,
                    'proof_token_hash' => hash('sha256', 'proof-token'),
                    'proof' => [
                        'intent' => [
                            'type' => 'agreement.sign',
                            'nonce' => 'signed-nonce',
                            'resource' => $signedResource,
                        ],
                    ],
                ],
                'agreement_signing_entity_l1_address' => $entityAddress,
                'agreement_signing_nonce' => 'rotated-nonce',
                'agreement_signing_resource' => $rotatedResource,
                'agreement_signing_context' => $rotatedContext,
                'agreement_signing_started_at' => now()->toIso8601String(),
            ])
            ->postJson(route('partner.register.agreement.sign'), ['simple_l1_sign' => true])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('pending_moderation', $entity->refresh()->status);
        $this->assertSame($signedContext['context_hash'], data_get($entity->agreement_metadata, 'signing_context_hash'));
    }

    public function test_offer_signature_rejects_stale_login_proof_without_agreement_intent(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('a', 39);
        $keyAddress = 'sl1_'.str_repeat('b', 40);

        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => $keyAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => [
                'simple_l1' => [
                    'identity_rule' => 'external_identity_provider',
                ],
            ],
        ]);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Offer Test LLC',
            'inn' => '1234567890',
            'email' => 'offer@example.test',
            'status' => 'pending_signature',
            'is_active' => false,
            'agreement_metadata' => [],
        ]);
        $user->managedLegalEntities()->attach($entity->id, ['role' => 'admin']);
        $context = $this->agreementSigningContext($user, $entity);

        $this->actingAs($user)
            ->withSession([
                'partner_registration' => [
                    'is_b2b' => true,
                    'legal_name' => 'Offer Test LLC',
                    'signer_role' => 'ceo',
                    'signer_name' => 'Offer Signer',
                    'business_email' => 'offer@example.test',
                ],
                'simple_l1_identity' => [
                    'entity_l1_address' => $entityAddress,
                    'key_l1_address' => $keyAddress,
                    'proof_token_hash' => hash('sha256', 'proof-token'),
                    'proof' => [
                        'intent' => [
                            'type' => 'identity.login',
                        ],
                    ],
                ],
                'agreement_signing_entity_l1_address' => $entityAddress,
                'agreement_signing_nonce' => 'agreement-nonce',
                'agreement_signing_resource' => 'agreement_context:'.$context['context_hash'],
                'agreement_signing_context' => $context,
                'agreement_signing_started_at' => now()->toIso8601String(),
            ])
            ->postJson(route('partner.register.agreement.sign'), ['simple_l1_sign' => true])
            ->assertUnprocessable()
            ->assertJsonFragment(['error' => 'Fresh agreement signing proof is required.']);

        $this->assertSame('pending_signature', $entity->refresh()->status);
        $this->assertNull($entity->agreement_signature);
    }

    public function test_business_registration_verifies_email_before_inn_flow(): void
    {
        Mail::fake();
        config(['app.redeem_local_verification_code' => 'TRUSTED_USER']);

        $this->postJson(route('business.register.email.send'), [
            'email' => 'founder@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(\App\Mail\VerificationCodeMail::class);

        $this->postJson(route('business.register.email.verify'), [
            'email' => 'founder@example.test',
            'code' => 'TRUSTED_USER',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('email', 'founder@example.test');

        $this->assertSame('founder@example.test', session('business_registration_verified_email'));
    }

    public function test_cabinet_registration_redirects_to_sl1e_identity_without_local_passkey(): void
    {
        $this->get('/vault/register')
            ->assertRedirect(route('meanly.simple_l1.connect', [
                'return_to' => route('cabinet.dashboard', [], false),
                'mode' => 'connect',
            ]));
    }

    public function test_local_passkey_registration_options_are_retired(): void
    {
        $this->postJson('/business/register/options', [
            'registration_target' => 'profile',
            'display_name' => 'selim',
        ])
            ->assertStatus(410)
            ->assertJsonPath('error', 'Local passkey registration options were retired. Use SL1E Identity instead.');
    }

    public function test_l1_identity_service_keeps_entity_address_separate_from_device_key(): void
    {
        $service = app(\App\Services\L1IdentityService::class);

        $entityAddress = $service->newEntityAddress();
        $keyAddress = $service->keyAddressFromPublicKey('test-public-key');

        $this->assertStringStartsWith('sl1e_', $entityAddress);
        $this->assertStringStartsWith('sl1_', $keyAddress);
        $this->assertNotSame($entityAddress, $keyAddress);
    }

    public function test_business_landing_is_public_and_links_to_registration(): void
    {
        $this->get('/business')
            ->assertOk()
            ->assertSee('Meanly для бизнеса')
            ->assertSee('Подключить бизнес')
            ->assertSee(route('business.register'));
    }

    public function test_connected_business_user_sees_console_cta_instead_of_connect_business(): void
    {
        $role = Role::firstOrCreate(['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Connected',
        ]);
        $user->assignRole($role);
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Connected Business LLC',
            'available_balance' => 0,
            'currency' => 'RUB',
            'status' => 'active',
            'is_active' => true,
            'inn' => '1234567890',
            'kpp' => '123456789',
            'ogrn' => '1234567890123',
            'bank_account' => '12345678901234567890',
            'bank_correspondent_account' => '12345678901234567890',
            'bank_name' => 'Connected Bank',
            'legal_address' => 'Connected Street 1',
            'postal_address' => 'Connected Street 1',
            'director_name' => 'Connected Director',
        ]);
        $user->managedLegalEntities()->attach($legalEntity->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Merchant Center', false)
            ->assertDontSee('Подключить бизнес', false);

        $this->actingAs($user)
            ->get('/business')
            ->assertOk()
            ->assertSee('Открыть Merchant Center', false)
            ->assertDontSee('Подключить бизнес', false);
    }

    public function test_legacy_partner_landing_redirects_to_business_landing(): void
    {
        $this->get('/partner-landing')
            ->assertRedirect(route('business.landing'));
    }

    public function test_legacy_legal_entities_registration_redirects_to_business_route(): void
    {
        $this->get('/legal-entities/register')
            ->assertRedirect(route('business.register'));
    }
}
