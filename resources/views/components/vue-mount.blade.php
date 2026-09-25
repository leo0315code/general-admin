@props(['component' => '', 'props' => []])

{{-- Vue 组件挂载点（统一契约）：
     data-vue-app + data-component + data-props(vue_props) 三件套，
     替代各页面手写挂载样板。用法：
     <x-vue-mount component="user-form" :props="$userFormProps" /> --}}
<div data-vue-app data-component="{{ $component }}" data-props='{!! vue_props($props) !!}'></div>
