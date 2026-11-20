{{-- Shared country form fields. The flag is chosen automatically from the ISO2 code. --}}
<div class="adm-form">
  <div class="adm-field">
    <label for="name">Country name *</label>
    <input type="text" id="name" name="name" value="{{ old('name', $country->name) }}" maxlength="100" required @class(['is-invalid' => $errors->has('name')])>
    @error('name') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field">
    <label for="iso2">ISO2 country code *</label>
    <input type="text" id="iso2" name="iso2" value="{{ old('iso2', $country->iso2) }}" minlength="2" maxlength="2" required placeholder="JP" @class(['is-invalid' => $errors->has('iso2')])>
    <small>Two letters, e.g. JP. The flag image is picked automatically from this code when the website has one.</small>
    @error('iso2') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field">
    <label for="slug">URL slug</label>
    <input type="text" id="slug" name="slug" value="{{ old('slug', $country->slug) }}" maxlength="120" @class(['is-invalid' => $errors->has('slug')])>
    <small>Used in /esim/&lt;slug&gt;. Leave empty to create it from the name.</small>
    @error('slug') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field">
    <label for="sort_order">Sort order</label>
    <input type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $country->sort_order ?? 0) }}" min="0" max="1000000" step="1">
    <small>Lower numbers are shown first. Default 0.</small>
    @error('sort_order') <span class="adm-error">{{ $message }}</span> @enderror
  </div>
  <div class="adm-field">
    <span class="adm-label">Options</span>
    <input type="hidden" name="is_active" value="0">
    <label class="adm-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $country->is_active))> Active</label>
    <input type="hidden" name="is_popular" value="0">
    <label class="adm-check"><input type="checkbox" name="is_popular" value="1" @checked(old('is_popular', $country->is_popular))> Popular destination (shown first on the home page)</label>
  </div>
</div>
