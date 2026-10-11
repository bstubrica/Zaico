<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Búsqueda rápida" subtitle="Escanea o escribe la etiqueta o el serial del equipo" icon="search">
            <x-slot name="actions">
                <a href="{{ route('activos.index') }}" class="btn btn-secondary">
                    <x-icon name="arrow-left" :size="18" /> Ver inventario
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3" x-data="escanerCodigos()">
        {{-- Entrada manual / lector de códigos --}}
        <div class="card card-body lg:col-span-2">
            <form id="form-buscar" method="GET" action="{{ route('activos.buscar') }}" class="space-y-4">
                <div>
                    <label for="codigo" class="form-label">Etiqueta o serial</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-slate-400">
                            <x-icon name="tag" :size="20" />
                        </span>
                        <input type="text" id="codigo" name="codigo" value="{{ old('codigo') }}"
                               placeholder="Ej. TBSM0001 o SN-123456"
                               class="form-control ps-11 text-lg" autofocus autocomplete="off" autocapitalize="off" spellcheck="false">
                    </div>
                    <p class="form-hint">
                        Los lectores de código de barras USB escriben aquí automáticamente. También puedes escanear con la cámara.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="search" :size="18" /> Buscar equipo
                    </button>
                    <button type="button" x-show="soportado" @click="iniciar()"
                            class="btn btn-secondary">
                        <x-icon name="camera" :size="18" /> Escanear con cámara
                    </button>
                </div>
            </form>
        </div>

        {{-- Panel de cámara --}}
        <div class="card card-body" x-show="soportado" x-cloak>
            <h3 class="card-title mb-3">Escáner de cámara</h3>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-900/5">
                <video x-ref="video" class="aspect-[4/3] w-full object-cover" muted playsinline></video>
            </div>

            <p x-show="escaneando" class="mt-3 text-sm text-slate-500">
                Apunta la cámara al código. La detección es automática.
            </p>
            <p x-show="!escaneando" class="mt-3 text-sm text-slate-500">
                Pulsa «Escanear con cámara» para usar la cámara del dispositivo.
            </p>

            <p x-show="error" x-text="error" class="mt-2 text-sm font-medium text-rose-600"></p>

            <button type="button" x-show="escaneando" @click="detener()" class="btn btn-danger mt-3 w-full">
                <x-icon name="close" :size="18" /> Detener
            </button>
        </div>

        {{-- Ayuda --}}
        <div class="card card-body lg:col-span-2">
            <h3 class="card-title mb-2">¿Cómo funciona?</h3>
            <ul class="list-disc space-y-1 ps-5 text-sm text-slate-600">
                <li>Escribe o escanea la <strong>etiqueta del activo</strong> (por ejemplo <span class="font-mono">TBSM0001</span>) o su <strong>serial</strong>.</li>
                <li>Si hay coincidencia, serás llevado directamente a la ficha del equipo.</li>
                <li>La búsqueda no distingue mayúsculas de minúsculas ni espacios al inicio o final.</li>
            </ul>
        </div>
    </div>

    @push('scripts')
        <script>
            window.escanerCodigos = () => ({
                soportado: typeof window.BarcodeDetector !== 'undefined',
                escaneando: false,
                error: null,
                stream: null,
                detector: null,
                async iniciar() {
                    this.error = null;
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: 'environment' },
                        });
                        this.$refs.video.srcObject = this.stream;
                        await this.$refs.video.play();
                        this.detector = new window.BarcodeDetector();
                        this.escaneando = true;
                        this.detectar();
                    } catch (e) {
                        this.error = 'No se pudo acceder a la cámara. Verifica los permisos del navegador.';
                        this.detener();
                    }
                },
                async detectar() {
                    if (!this.escaneando) {
                        return;
                    }
                    try {
                        const codigos = await this.detector.detect(this.$refs.video);
                        if (codigos.length > 0) {
                            const valor = codigos[0].rawValue;
                            this.detener();
                            const input = document.getElementById('codigo');
                            input.value = valor;
                            document.getElementById('form-buscar').submit();
                            return;
                        }
                    } catch (e) {
                        // Ignorar fotogramas sin detección.
                    }
                    requestAnimationFrame(() => this.detectar());
                },
                detener() {
                    this.escaneando = false;
                    if (this.stream) {
                        this.stream.getTracks().forEach((track) => track.stop());
                        this.stream = null;
                    }
                },
            });
        </script>
    @endpush
</x-app-layout>
