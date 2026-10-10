<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Perfil" subtitle="Administra tu información y seguridad" icon="user-circle" />
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-5">
        <div class="card card-body">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="card card-body">
            @include('profile.partials.update-password-form')
        </div>

        <div class="card card-body">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
