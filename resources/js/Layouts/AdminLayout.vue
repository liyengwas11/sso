<script setup>
import { usePage, Link } from "@inertiajs/vue3";
import { route } from "ziggy-js";
import { computed } from "vue";

const page = usePage();
const user = computed(() => page.props.auth.user);
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const unreviewedFlagsCount = computed(() => page.props.unreviewedFlagsCount ?? 0);

function can(permission) {
  return user.value?.permissions?.includes(permission);
}

const nav = [
  { label: "Dashboard", href: route("admin.dashboard"), permission: null },
  { label: "Events", href: route("admin.events.index"), permission: "manage-events" },
  { label: "Staff", href: route("admin.staff.index"), permission: "manage-users" },
  { label: "Roles & Permissions", href: route("admin.roles.index"), permission: "manage-roles" },
];
</script>

<template>
  <div class="min-h-screen flex bg-slate-50">
    <aside class="w-60 shrink-0 bg-slate-900 text-slate-200 flex flex-col">
      <div class="px-5 py-5 text-white font-semibold text-lg border-b border-slate-800">
        Event Check-in
      </div>

      <nav class="flex-1 px-2 py-4 space-y-1">
        <template v-for="item in nav" :key="item.href">
          <Link
            v-if="!item.permission || can(item.permission)"
            :href="item.href"
            class="block px-3 py-2 rounded-md text-sm hover:bg-slate-800 hover:text-white transition"
            :class="{ 'bg-slate-800 text-white': $page.url.startsWith(item.href) }"
          >
            {{ item.label }}
          </Link>
        </template>

        <Link
          v-if="can('manage-events')"
          :href="route('admin.flags.index')"
          class="flex items-center justify-between px-3 py-2 rounded-md text-sm hover:bg-slate-800 hover:text-white transition"
          :class="{ 'bg-slate-800 text-white': $page.url.startsWith('/admin/flags') }"
        >
          <span>Flags</span>
          <span
            v-if="unreviewedFlagsCount > 0"
            class="bg-red-500 text-white text-xs font-semibold rounded-full px-2 py-0.5"
          >
            {{ unreviewedFlagsCount }}
          </span>
        </Link>
      </nav>

      <div class="px-4 py-4 border-t border-slate-800">
        <p class="text-xs text-slate-400 mb-2 truncate">{{ user?.name }}</p>
        <Link
          :href="route('logout')"
          method="post"
          as="button"
          class="w-full text-left text-sm text-slate-300 hover:text-white"
        >
          Sign out
        </Link>
      </div>
    </aside>

    <div class="flex-1 flex flex-col min-w-0">
      <div v-if="flashSuccess || flashError" class="px-8 pt-6">
        <div
          v-if="flashSuccess"
          class="mb-2 text-sm text-green-800 bg-green-50 border border-green-200 rounded px-3 py-2"
        >
          {{ flashSuccess }}
        </div>
        <div
          v-if="flashError"
          class="mb-2 text-sm text-red-800 bg-red-50 border border-red-200 rounded px-3 py-2"
        >
          {{ flashError }}
        </div>
      </div>

      <main class="flex-1 px-8 py-6">
        <slot />
      </main>
    </div>
  </div>
</template>
