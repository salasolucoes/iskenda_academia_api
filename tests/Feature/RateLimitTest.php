<?php

use App\Models\User;
use Domain\Support\Contracts\AuditLoggerInterface;
use Domain\Support\ValueObjects\ActorContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    // O limiter vive no cache: sem flush, o balde de um teste vaza para o
    // seguinte e os limites parecem estar a funcionar sozinhos.
    Cache::flush();
});

/**
 * `auth` = 5/15min por email + 20/15min por IP
 * `otp`  = 5/10min (verificação) + 5/15min por email (envio) + 20/min por IP
 */
describe('limiter auth — chave dupla (email + IP)', function () {
    test('bloqueia ao 6.º login da mesma identidade', function () {
        $payload = ['email' => 'bruta@test.com', 'password' => 'Errada123!'];

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422)
                ->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJson(['code' => 'RATE_LIMIT_EXCEEDED']);
    });

    test('o limite por email não é contornado por variação de capitalização', function () {
        // Regressão: sem normalização, cada capitalização caía num balde novo e
        // o limite de 5 tornava-se inexistente.
        foreach (['bruta@test.com', 'Bruta@Test.com', 'BRUTA@TEST.COM', 'bruta@TEST.com', 'bruta@test.com '] as $email) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $email,
                'password' => 'Errada123!',
            ])->assertStatus(422)
                ->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'bruta@test.com',
            'password' => 'Errada123!',
        ])->assertStatus(429);
    });

    test('identidades diferentes no mesmo IP partilham o limite de rede', function () {
        // 20 pedidos por IP, mesmo que cada email seja distinto: sem a segunda
        // chave, um atacante com um IP fixo teria 5 tentativas por cada email
        // que conseguir inventar.
        foreach (range(1, 20) as $index) {
            $this->postJson('/api/v1/auth/login', [
                'email' => "alvo{$index}@test.com",
                'password' => 'Errada123!',
            ])->assertStatus(422)
                ->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'alvo21@test.com',
            'password' => 'Errada123!',
        ])->assertStatus(429);
    });

    test('o limite por identidade não atinge utilizadores legítimos diferentes', function () {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'a@test.com',
            'password' => 'Errada123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');

        // Balde novo, mesmo IP, mesmo instante: continua 401 e não 429.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'b@test.com',
            'password' => 'Errada123!',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    });

    test('um login bem-sucedido também consome a tentativa', function () {
        // Sem isto, o atacante usaria passwords correctas de outras contas
        // como cobertura e o balde nunca encheria.
        User::factory()->create([
            'email' => 'legitimo@test.com',
            'password' => bcrypt('S3nh@F0rte'),
        ]);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'legitimo@test.com',
                'password' => 'S3nh@F0rte',
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'legitimo@test.com',
            'password' => 'S3nh@F0rte',
        ])->assertStatus(429);
    });
});

describe('limiter otp — chave dupla (email + IP)', function () {
    test('bloqueia o 6.º envio de forgot-password para a mesma identidade', function () {
        User::factory()->create(['email' => 'reset@test.com']);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'reset@test.com',
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@test.com'])
            ->assertStatus(429);
    });

    test('bloqueia ao 21.º pedido de envio a partir do mesmo IP', function () {
        // Custo de infraestrutura: o envio gera um email real. 20/min por IP
        // corta o scraper antes que ele vire centenas de milhares de emails.
        // Emails distintos de propósito — caso contrário seria o balde de
        // envio por identidade a estourar primeiro e o IP nunca seria testado.
        foreach (range(1, 20) as $index) {
            $this->postJson('/api/v1/auth/forgot-password', [
                'email' => "spam{$index}@test.com",
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'spam21@test.com'])
            ->assertStatus(429);
    });

    test('o envio não é confundido com a verificação', function () {
        $user = User::factory()->create([
            'email' => 'misto@test.com',
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        // Dez verificações erradas: esgota o balde de verificação...
        foreach (range(1, 10) as $ignored) {
            $this->postJson('/api/v1/auth/verify-reset-otp', [
                'email' => $user->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/verify-reset-otp', [
            'email' => $user->email,
            'otp_code' => '000000',
        ])->assertStatus(429);

        // ...mas o pedido de reenvio tem balde próprio e continua disponível.
        // Era este o objectivo: sem a separação, o utilizador ficava preso.
        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk();
    });

    test('a verificação de OTP é limitada por identidade e IP combinados', function () {
        $user = User::factory()->create([
            'email' => 'alvo@test.com',
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'email' => $user->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        // 5 tentativas erradas invalidaram o código (limite do domínio), logo o
        // contador voltou a zero. As 5 seguintes passam pelo HTTP, mas já não
        // encontram código nenhum: cada camada conta uma coisa diferente.
        $user->refresh();
        expect($user->otp_code)->toBeNull()
            ->and($user->otp_attempts)->toBe(0);

        foreach (range(6, 10) as $ignored) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'email' => $user->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        // 11.ª tentativa: agora é o HTTP que corta.
        $this->postJson('/api/v1/auth/verify-otp', [
            'email' => $user->email,
            'otp_code' => '000000',
        ])->assertStatus(429);
    });

    test('o limite HTTP não canibaliza o limite por código do domínio', function () {
        // Regressão de arquitectura: se `otp-verify` também cortasse aos 5, o
        // limite do domínio (que invalida o código) nunca seria alcançado via
        // HTTP e a invalidação ficaria ser código morto.
        $user = User::factory()->create([
            'email' => 'ordem@test.com',
            'otp_code' => '123456',
            'otp_expires_at' => now()->addMinutes(10),
        ]);

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/verify-otp', [
                'email' => $user->email,
                'otp_code' => '000000',
            ])->assertStatus(422);
        }

        // O código foi invalidado pelo domínio, dentro da janela HTTP.
        $this->assertNull($user->fresh()->otp_code);
    });
});

describe('teto global e limiters de custo', function () {
    test('o teto global corta um flood sobre qualquer rota pública', function () {
        // 60/min por IP quando não há sessão: nenhuma rota fica sem limite.
        foreach (range(1, 60) as $ignored) {
            $this->getJson('/api/v1/categories')->assertOk();
        }

        $this->getJson('/api/v1/categories')
            ->assertStatus(429)
            ->assertJson(['code' => 'RATE_LIMIT_EXCEEDED']);
    });

    test('o limite de progresso corta o flood de ping de video', function () {
        $student = User::factory()->create(['email_verified_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.$student->createToken('t')->plainTextToken];

        // 30/5min. O payload e invalido de proposito: o limiter corre antes do
        // controller, e o que se quer testar e a contagem, nao a regra de negocio.
        foreach (range(1, 30) as $ignored) {
            $this->withHeaders($headers)
                ->postJson('/api/v1/classroom/00000000-0000-0000-0000-000000000000/progress', [])
                ->assertStatus(422);
        }

        $this->withHeaders($headers)
            ->postJson('/api/v1/classroom/00000000-0000-0000-0000-000000000000/progress', [])
            ->assertStatus(429);
    });

    test('o limite de PDF corta a emissão repetida de certificados', function () {
        $student = User::factory()->create(['email_verified_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.$student->createToken('t')->plainTextToken];

        // O enrollment não existe, logo 404: confirma que o pedido chegou ao
        // controller em vez de ser barrado. O que se conta aqui é o limiter.
        foreach (range(1, 5) as $ignored) {
            $this->withHeaders($headers)
                ->postJson('/api/v1/certificates/00000000-0000-0000-0000-000000000000/issue', [])
                ->assertNotFound();
        }

        // DOMPDF é das operações mais caras da aplicação: 6.º pedido no minuto.
        $this->withHeaders($headers)
            ->postJson('/api/v1/certificates/00000000-0000-0000-0000-000000000000/issue', [])
            ->assertStatus(429);
    });

    test('o limite de broadcast restringe o admin a 5 por 15 minutos', function () {
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.$admin->createToken('t')->plainTextToken];

        foreach (range(1, 5) as $ignored) {
            $this->withHeaders($headers)
                ->postJson('/api/v1/admin/notifications/broadcast', [])
                ->assertStatus(422);
        }

        $this->withHeaders($headers)
            ->postJson('/api/v1/admin/notifications/broadcast', [])
            ->assertStatus(429);
    });

    test('o limite é por utilizador — um não bloqueia o outro', function () {
        $student = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        foreach (range(1, 30) as $ignored) {
            $this->withHeaders(['Authorization' => 'Bearer '.$student->createToken('t')->plainTextToken])
                ->postJson('/api/v1/classroom/00000000-0000-0000-0000-000000000000/progress', [])
                ->assertStatus(422);
        }

        // Se a chave fosse por IP, o colega de trás do mesmo CGNAT ficaria
        // bloqueado junto. Em Angola isso significaria a plataforma fora para
        // todos os utilizadores de um operador ao mesmo tempo.
        $this->withHeaders(['Authorization' => 'Bearer '.$other->createToken('t')->plainTextToken])
            ->postJson('/api/v1/classroom/00000000-0000-0000-0000-000000000000/progress', [])
            ->assertStatus(422);
    });
});

describe('IP real por tras do proxy', function () {
    test('dois clientes distintos atras do mesmo proxy nao se bloqueiam', function () {
        // Este é o cenário real: mesmo endereço de proxy (o Cloudflare), dois
        // clientes diferentes. Sem `trustProxies`, `$request->ip()` devolvia o
        // IP do proxy nos dois casos e o segundo herdava o balde esgotado do
        // primeiro — o limite por rede virava um limite global e, com um CGNAT
        // angolano, tirava a plataforma do ar a um grupo inteiro de alunos.
        foreach (range(1, 20) as $index) {
            $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.1'])
                ->withHeader('X-Forwarded-For', '203.0.113.10')
                ->postJson('/api/v1/auth/login', [
                    'email' => "proxy{$index}@test.com",
                    'password' => 'Errada123!',
                ])
                ->assertStatus(422);
        }

        // O primeiro cliente esgotou o seu balde de rede.
        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.10')
            ->postJson('/api/v1/auth/login', [
                'email' => 'proxy21@test.com',
                'password' => 'Errada123!',
            ])
            ->assertStatus(429);

        // Um segundo cliente, mesmo proxy, outro IP real: balde limpo.
        $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.1'])
            ->withHeader('X-Forwarded-For', '198.51.100.50')
            ->postJson('/api/v1/auth/login', [
                'email' => 'outro-cliente@test.com',
                'password' => 'Errada123!',
            ])
            ->assertStatus(422);
    });

    test('um IP de origem nao confiado nao consegue forjar o X-Forwarded-For', function () {
        // `trustProxies` com lista explícita. Se estivesse em `at: '*'`, cada
        // `X-Forwarded-For` forjado criaria o seu próprio balde e o limite por
        // rede não existiria. Aqui a origem não é do Cloudflare, o cabeçalho é
        // ignorado, e vinte IPs forjados diferentes continuam a somar no mesmo
        // balde — o do endereço real de quem fez o pedido.
        foreach (range(1, 20) as $index) {
            $this->withHeader('X-Forwarded-For', '198.51.100.'.$index)
                ->postJson('/api/v1/auth/login', [
                    'email' => "spoof{$index}@test.com",
                    'password' => 'Errada123!',
                ])
                ->assertStatus(422);
        }

        // Se o cabeçalho tivesse sido acreditado, estes 20 pedidos estariam
        // espalhados por 20 baldes e nada seria bloqueado agora.
        $this->withHeader('X-Forwarded-For', '198.51.100.99')
            ->postJson('/api/v1/auth/login', [
                'email' => 'spoof21@test.com',
                'password' => 'Errada123!',
            ])
            ->assertStatus(429);
    });
});

describe('auditoria de rate_limit.exceeded', function () {
    test('o 429 fica registado com rota, limiter e limite', function () {
        $payload = ['email' => 'auditoria@test.com', 'password' => 'Errada123!'];

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);

        $log = DB::table('audit_logs')
            ->where('event_type', 'rate_limit.exceeded')
            ->orderByDesc('id')
            ->first();

        expect($log)->not->toBeNull();

        $state = json_decode($log->new_state, true);

        expect($state['limiter'])->toBe('auth')
            ->and($state['route'])->toBe('POST api/v1/auth/login')
            ->and($state['max_attempts'])->toBe(5)
            ->and($log->actor_ip)->not->toBeNull();
    });

    test('a auditoria identifica o utilizador autenticado', function () {
        $student = User::factory()->create(['email_verified_at' => now()]);
        $headers = ['Authorization' => 'Bearer '.$student->createToken('t')->plainTextToken];

        foreach (range(1, 30) as $ignored) {
            $this->withHeaders($headers)
                ->postJson('/api/v1/classroom/00000000-0000-0000-0000-000000000000/progress', [])
                ->assertStatus(422);
        }

        $this->withHeaders($headers)
            ->postJson('/api/v1/classroom/00000000-0000-0000-0000-000000000000/progress', [])
            ->assertStatus(429);

        $log = DB::table('audit_logs')
            ->where('event_type', 'rate_limit.exceeded')
            ->orderByDesc('id')
            ->first();

        expect($log->actor_id)->toBe($student->id)
            ->and(json_decode($log->new_state, true)['limiter'])->toBe('progress');
    });

    test('uma falha de auditoria não transforma o 429 num 500', function () {
        // A auditoria é um extra: se ela cair a meio, o cliente continua a
        // receber 429. O oposto — um 500 em cascata durante um ataque — seria
        // precisamente o que multiplicaria o estrago.
        //
        // Só `rate_limit.exceeded` rebenta. O mesmo logger regista
        // `auth.login.failed`, e rebentar aí a transformaria a falha de
        // credenciais num 500, que não é o que se está a testar.
        $real = $this->app->make(AuditLoggerInterface::class);

        $this->app->bind(AuditLoggerInterface::class, fn () => new class($real) implements AuditLoggerInterface
        {
            public function __construct(
                private AuditLoggerInterface $inner,
            ) {}

            public function log(
                string $eventType,
                mixed $auditable,
                ActorContext $actor,
                ?array $previousState = null,
                ?array $newState = null,
            ): void {
                if ($eventType === 'rate_limit.exceeded') {
                    throw new RuntimeException('auditoria indisponível');
                }

                $this->inner->log($eventType, $auditable, $actor, $previousState, $newState);
            }
        });

        $payload = ['email' => 'auditoria-falha@test.com', 'password' => 'Errada123!'];

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', $payload)
            ->assertStatus(429)
            ->assertJson(['code' => 'RATE_LIMIT_EXCEEDED']);
    });
});

describe('resposta 429 no contrato da API', function () {
    test('inclui os headers de Retry-After e X-RateLimit-*', function () {
        $payload = ['email' => 'headers@test.com', 'password' => 'Errada123!'];

        foreach (range(1, 5) as $ignored) {
            $this->postJson('/api/v1/auth/login', $payload)->assertStatus(422)
                ->assertJsonValidationErrors('email');
        }

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(429)
            ->assertJson([
                'message' => 'Demasiadas tentativas. Tente novamente mais tarde.',
                'errors' => [],
                'code' => 'RATE_LIMIT_EXCEEDED',
            ])
            ->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit');

        expect($response->headers->get('Retry-After'))->not->toBeNull();
    });
});
