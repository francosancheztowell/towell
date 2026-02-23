{{--
    Componente: Login Form

    Descripción:
        Formulario de login reutilizable con validación y autenticación.

    Props:
        @param string $action - URL de acción del formulario (default: '/login')
        @param string $method - Método HTTP (default: 'POST')
        @param string $successMessage - Mensaje de éxito (opcional)

    Uso:
        <x-auth.login-form />
--}}

@props([
    'action' => '/login',
    'method' => 'POST',
    'successMessage' => null
])

<div class="login-form-container">
    <!-- Mostrar errores de sesión -->
    @if (session('error'))
        <x-ui.alert type="error" :message="session('error')" />
    @endif

    <!-- Mostrar mensaje de éxito -->
    @if ($successMessage)
        <x-ui.alert type="success" :message="$successMessage" />
    @endif

    <form id="loginForm" method="{{ $method }}" action="{{ $action }}">
        @csrf

        <!-- Campo Número de Empleado -->
        <div class="mb-5">
            <label for="numero_empleado" class="block text-sm font-medium text-gray-700 mb-2">
                Número de Empleado
            </label>
            <input
                type="number"
                name="numero_empleado"
                id="numero_empleado"
                placeholder="Ingresa tu número de empleado"
                required
                class="w-full px-4 py-4 border border-gray-300 rounded-lg text-base transition-all duration-200 bg-white focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
            />
        </div>

        <!-- Campo Contraseña -->
        <div class="mb-5">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                Contraseña
            </label>

            <div class="relative">
                <input
                    type="password"
                    inputmode="numeric"
                    name="contrasenia"
                    id="password"
                    placeholder="Ingresa tu contraseña numérica"
                    required
                    pattern="[0-9]*"
                    class="w-full px-4 py-4 pr-12 border border-gray-300 rounded-lg text-base transition-all duration-200 bg-white focus:outline-none focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                />

                <!-- Botón para mostrar/ocultar contraseña -->
                <button
                    type="button"
                    id="togglePassword"
                    class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors duration-200"
                >
                    <svg id="eyeClosed" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    <svg id="eyeOpen" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Botón de envío -->
        <x-ui.button
            type="submit"
            variant="primary"
            size="lg"
            fullWidth="true"
            class="mb-4"
        >
            Iniciar Sesión
        </x-ui.button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleButton = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    const eyeClosed = document.getElementById('eyeClosed');
    const eyeOpen = document.getElementById('eyeOpen');

    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            const isPassword = passwordField.type === 'password';

            passwordField.type = isPassword ? 'text' : 'password';
            eyeClosed.classList.toggle('hidden', !isPassword);
            eyeOpen.classList.toggle('hidden', isPassword);
        });
    }
});
</script>
