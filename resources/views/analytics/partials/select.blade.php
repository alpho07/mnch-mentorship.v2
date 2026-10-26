{{-- Auto-submitting filter dropdown. $name, $label, $options (value => label), $selected, $all (label for empty value) --}}
<div class="fld"><label for="f-{{ $name }}">{{ $label }}</label><div class="selw">
<select class="sel" id="f-{{ $name }}" name="{{ $name }}" onchange="this.form.requestSubmit()" @if (! empty($disabled)) disabled @endif>
<option value="">{{ $all }}</option>
@foreach ($options as $value => $text)
<option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
@endforeach
</select>@include('analytics.partials.icon', ['name' => 'chevron-down', 'size' => 16, 'sw' => 2, 'style' => 'position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:#556B6E'])
</div></div>
