@props(['categories', 'selected' => null])
<label class="form-label">{{ __('lblDocType') }}</label>
<select class="form-select" name="category_id" required>
    @foreach ($categories as $category)
        <option value="{{ $category->id }}" @selected(old('category_id', $selected) == $category->id)>{{ $category->name }}</option>
    @endforeach
</select>
