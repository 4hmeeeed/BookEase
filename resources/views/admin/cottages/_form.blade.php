@php
    $currentImage = $cottage->image_url;
@endphp

<div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
    {{-- Details --}}
    <div class="card space-y-6 p-6 sm:p-8">
        <div>
            <x-input-label for="name" value="Unit name" />
            <x-text-input id="name" name="name" class="mt-1.5" :value="old('name', $cottage->name)" required maxlength="255" autofocus placeholder="e.g. Seaside Cottage 3" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div class="grid gap-6 sm:grid-cols-3">
            <div>
                <x-input-label for="type" value="Type" />
                <select id="type" name="type" class="field mt-1.5" required @if($errors->has('type')) aria-invalid="true" @endif>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $cottage->type?->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="capacity" value="Max guests" />
                <x-text-input id="capacity" name="capacity" type="number" min="1" max="100" class="mt-1.5" :value="old('capacity', $cottage->capacity)" required />
                <x-input-error :messages="$errors->get('capacity')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="price_per_night" value="Nightly rate" />
                <div class="relative mt-1.5">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 font-semibold text-gray-500">₱</span>
                    <x-text-input id="price_per_night" name="price_per_night" inputmode="decimal" class="pl-8" :value="old('price_per_night', $cottage->price_per_night)" required placeholder="2500.00" />
                </div>
                <x-input-error :messages="$errors->get('price_per_night')" class="mt-2" />
            </div>
        </div>

        <div>
            <x-input-label for="description" value="Description" />
            <textarea id="description" name="description" rows="5" maxlength="5000" class="field mt-1.5"
                      placeholder="What makes this unit special: view, beds, amenities, and inclusions."
                      @if($errors->has('description')) aria-invalid="true" @endif>{{ old('description', $cottage->description) }}</textarea>
            <p class="mt-1.5 text-xs text-gray-500">Guests see this, and the chatbot uses it to answer questions, so keep it accurate.</p>
            <x-input-error :messages="$errors->get('description')" class="mt-2" />
        </div>

        <label class="flex cursor-pointer items-center justify-between gap-4 rounded-2xl border border-sand-200 bg-sand-50 p-4">
            <span>
                <span class="block font-semibold text-lagoon-900">Listed for guests</span>
                <span class="block text-sm text-gray-600">Turn off to hide this unit without deleting it.</span>
            </span>
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="peer sr-only" @checked(old('is_active', $cottage->is_active))>
            <span class="relative h-7 w-12 shrink-0 rounded-full bg-gray-300 transition-colors duration-300 peer-checked:bg-lagoon-600 peer-focus-visible:ring-2 peer-focus-visible:ring-lagoon-400 peer-focus-visible:ring-offset-2
                         after:absolute after:left-1 after:top-1 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform after:duration-300 after:ease-spring peer-checked:after:translate-x-5"></span>
        </label>
    </div>

    {{-- Photo --}}
    <div x-data="{ preview: @js($currentImage), removed: false,
                   pick(e) { const f = e.target.files[0]; if (f) { this.preview = URL.createObjectURL(f); this.removed = false } } }"
         class="space-y-3">
        <span class="block text-sm font-semibold text-lagoon-900">Photo</span>

        <label class="group relative block aspect-[4/3] cursor-pointer overflow-hidden rounded-3xl border-2 border-dashed border-sand-300 bg-white/70 transition hover:border-lagoon-400">
            <template x-if="preview && ! removed">
                <img :src="preview" alt="Photo preview" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
            </template>
            <div x-show="! preview || removed" class="absolute inset-0 grid place-items-center p-6 text-center">
                <div>
                    <svg class="mx-auto h-10 w-10 text-lagoon-400 transition-transform duration-500 ease-spring group-hover:-translate-y-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2.5" /><circle cx="8.5" cy="10" r="1.5" /><path stroke-linecap="round" stroke-linejoin="round" d="m21 16-5-5-8 8" /></svg>
                    <p class="mt-2 text-sm font-semibold text-lagoon-800">Choose a photo</p>
                    <p class="text-xs text-gray-500">JPG, PNG, or WebP · up to 4 MB</p>
                </div>
            </div>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick">
        </label>
        <x-input-error :messages="$errors->get('image')" class="mt-2" />

        @if ($currentImage)
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remove_image" value="1" x-model="removed" class="rounded border-sand-300 text-coral-600 focus:ring-coral-500">
                Remove the current photo
            </label>
        @endif
    </div>
</div>
