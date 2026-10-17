<template>
  <div class="min-h-screen bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex">
            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
              <Link :href="route('dashboard')" class="text-xl font-bold text-indigo-600">
                YouTube Video Generator
              </Link>
            </div>

            <a href="/integrations/chatgpt" class="self-center ml-4 text-sm text-indigo-600">ChatGPT</a>
            <!-- Primary Navigation Menu -->
            <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
              <NavLink :href="route('dashboard')" :active="route().current('dashboard')">
                Dashboard
              </NavLink>
              <NavLink :href="route('videos.index')" :active="route().current('videos.*')">
                Videos
              </NavLink>
              <NavLink :href="route('videos.create')" :active="route().current('videos.create')">
                Generate Video
              </NavLink>
            </div>
          </div>

          <div class="hidden sm:flex sm:items-center sm:ml-6">
            <!-- User Quota Display -->
            <div v-if="$page.props.auth.user" class="mr-4 text-sm text-gray-600">
              {{ remainingVideos }} videos remaining this month
            </div>

            <!-- Settings Dropdown -->
            <div class="ml-3 relative">
              <Dropdown align="right" width="48">
                <template #trigger>
                  <span class="inline-flex rounded-md">
                    <button
                      type="button"
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150"
                    >
                      <img
                        v-if="$page.props.auth.user.avatar_url"
                        :src="$page.props.auth.user.avatar_url"
                        :alt="$page.props.auth.user.name"
                        class="h-6 w-6 rounded-full mr-2"
                      />
                      <div v-else class="h-6 w-6 bg-gray-300 rounded-full mr-2 flex items-center justify-center">
                        <svg class="h-4 w-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                      </div>
                      {{ $page.props.auth.user.name }}

                      <svg
                        class="ml-2 -mr-0.5 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                      >
                        <path
                          fill-rule="evenodd"
                          d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                          clip-rule="evenodd"
                        />
                      </svg>
                    </button>
                  </span>
                </template>

                <template #content>
                  <DropdownLink :href="route('profile.edit')"> Profile </DropdownLink>
                  <DropdownLink :href="route('logout')" method="post" as="button">
                    Log Out
                  </DropdownLink>
                </template>
              </Dropdown>
            </div>
          </div>

          <!-- Hamburger -->
          <div class="-mr-2 flex items-center sm:hidden">
            <button
              @click="showingNavigationDropdown = !showingNavigationDropdown"
              class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out"
            >
              <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                <path
                  :class="{
                    hidden: showingNavigationDropdown,
                    'inline-flex': !showingNavigationDropdown,
                  }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M4 6h16M4 12h16M4 18h16"
                />
                <path
                  :class="{
                    hidden: !showingNavigationDropdown,
                    'inline-flex': showingNavigationDropdown,
                  }"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Responsive Navigation Menu -->
      <div
        :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
        class="sm:hidden"
      >
        <div class="pt-2 pb-3 space-y-1">
          <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
            Dashboard
          </ResponsiveNavLink>
          <ResponsiveNavLink :href="route('videos.index')" :active="route().current('videos.*')">
            Videos
          </ResponsiveNavLink>
          <ResponsiveNavLink :href="route('videos.create')" :active="route().current('videos.create')">
            Generate Video
          </ResponsiveNavLink>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
          <div class="px-4">
            <div class="font-medium text-base text-gray-800">
              {{ $page.props.auth.user.name }}
            </div>
            <div class="font-medium text-sm text-gray-500">{{ $page.props.auth.user.email }}</div>
          </div>

          <div class="mt-3 space-y-1">
            <ResponsiveNavLink :href="route('profile.edit')"> Profile </ResponsiveNavLink>
            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
              Log Out
            </ResponsiveNavLink>
          </div>
        </div>
      </div>
    </nav>

    <!-- Page Heading -->
    <header class="bg-white shadow" v-if="$slots.header">
      <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <slot name="header" />
      </div>
    </header>

    <!-- Page Content -->
    <main>
      <slot />
    </main>

    <!-- Flash Messages -->
    <div v-if="$page.props.flash.message" class="fixed bottom-4 right-4 z-50">
      <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
        {{ $page.props.flash.message }}
      </div>
    </div>

    <div v-if="$page.props.flash.error" class="fixed bottom-4 right-4 z-50">
      <div class="bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
        {{ $page.props.flash.error }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import NavLink from '@/Components/NavLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'

const showingNavigationDropdown = ref(false)
const page = usePage()

const remainingVideos = computed(() => {
  const user = page.props.auth.user
  if (!user) return 0
  return Math.max(0, (user.monthly_video_quota || 10) - (user.videos_generated_this_month || 0))
})
</script>
