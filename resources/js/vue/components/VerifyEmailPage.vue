<script setup>
// 邮箱验证提示页（auth/verify-email）
defineProps({
    sendUrl: { type: String, default: '' },
    logoutUrl: { type: String, default: '' },
    csrf: { type: String, default: '' },
    statusSent: { type: Boolean, default: false },
});
</script>

<template>
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        感谢您注册！在开始使用之前，请点击我们发送到您邮箱的验证链接来确认邮箱地址。如果没收到邮件，我们可以为您重新发送一封。
    </div>

    <div v-if="statusSent" class="mb-4 font-medium text-sm text-success-600 dark:text-success-400">
        新的验证链接已发送到您注册时填写的邮箱地址。
    </div>

    <div class="mt-4 flex items-center justify-between">
        <form :action="sendUrl" method="POST">
            <input type="hidden" name="_token" :value="csrf">
            <button type="submit" class="btn-primary">重新发送验证邮件</button>
        </form>

        <form :action="logoutUrl" method="POST">
            <input type="hidden" name="_token" :value="csrf">
            <button type="submit" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 underline">
                退出登录
            </button>
        </form>
    </div>
</template>
