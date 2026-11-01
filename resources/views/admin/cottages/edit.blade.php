<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('admin.cottages.index') }}" class="link-underline text-sm">&larr; Cottages & rates</a>
        <div class="mt-3"><x-page-header eyebrow="Edit unit" :title="$cottage->name" /></div>
    </x-slot>

    <form method="POST" action="{{ route('admin.cottages.update', $cottage) }}" enctype="multipart/form-data" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @csrf
        @method('PUT')
        @include('admin.cottages._form')

        <div class="mt-8 flex justify-end gap-3">
            <a href="{{ route('admin.cottages.index') }}" class="btn-ghost">Cancel</a>
            <x-primary-button>Save changes</x-primary-button>
        </div>
    </form>
</x-app-layout>
