<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La tabla ya existe en MySQL. Reproducimos los campos del alta en SQLite.
        Schema::create('usuarios', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('nombre', 100);
            $table->string('apellidos', 150);
            $table->string('email')->unique();
            $table->string('password');
            $table->string('telefono', 20)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('localidad', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('rol')->default('cliente');
            $table->timestamps();
        });
    }

    public function test_registra_en_usuarios_e_inicia_sesion_con_el_carrito(): void
    {
        $this->get('/registro')->assertOk();

        $this->withSession(['carrito' => [7 => 2]])
            ->post('/registro', [
                'nombre' => 'Ana',
                'apellidos' => 'García',
                'email' => 'ana@example.com',
                'password' => 'clave-de-prueba',
                'password_confirmation' => 'clave-de-prueba',
                'telefono' => '+34 612 345 678',
                'direccion' => 'Calle Ancha 10',
                'codigo_postal' => '04001',
                'localidad' => 'Almería',
                'provincia' => 'Almería',
                'rol' => 'admin',
            ])
            ->assertRedirect('/')
            ->assertSessionHas('carrito', [7 => 2]);

        $usuario = Usuario::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('cliente', $usuario->rol);
        $this->assertSame('+34 612 345 678', $usuario->telefono);
        $this->assertSame('Calle Ancha 10', $usuario->direccion);
        $this->assertSame('04001', $usuario->codigo_postal);
        $this->assertSame('Almería', $usuario->localidad);
        $this->assertSame('Almería', $usuario->provincia);
        $this->assertTrue(Hash::check('clave-de-prueba', $usuario->password));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_registro_exige_la_direccion_habitual(): void
    {
        $this->from('/registro')->post('/registro', [
            'nombre' => 'Ana',
            'apellidos' => 'García',
            'email' => 'ana@example.com',
            'password' => 'clave-de-prueba',
            'password_confirmation' => 'clave-de-prueba',
        ])->assertRedirect('/registro')
            ->assertSessionHasErrors(['direccion', 'codigo_postal', 'localidad', 'provincia']);

        $this->assertDatabaseCount('usuarios', 0);
        $this->assertGuest();
    }

    public function test_solo_inicia_sesion_con_la_contrasena_correcta_y_conserva_el_carrito(): void
    {
        $usuario = Usuario::create([
            'nombre' => 'Ana',
            'apellidos' => 'García',
            'email' => 'ana@example.com',
            'password' => 'clave-de-prueba',
        ]);

        $this->get('/entrar')->assertOk();
        $this->from('/entrar')->post('/entrar', [
            'email' => $usuario->email,
            'password' => 'incorrecta',
        ])->assertRedirect('/entrar')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->withSession(['carrito' => [7 => 2]])->post('/entrar', [
            'email' => $usuario->email,
            'password' => 'clave-de-prueba',
        ])->assertRedirect('/')->assertSessionHas('carrito', [7 => 2]);
        $this->assertAuthenticatedAs($usuario);
    }
}
