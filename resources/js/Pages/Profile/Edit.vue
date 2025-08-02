<template>
  <AuthenticatedLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <!-- Profile Information -->
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
          <div class="max-w-xl">
            <h2 class="text-lg font-medium text-gray-900">Profile Information</h2>
            <p class="mt-1 text-sm text-gray-600">
              Update your account's profile information and email address.
            </p>

            <form @submit.prevent="updateProfile" class="mt-6 space-y-6">
              <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input
                  id="name"
                  v-model="form.name"
                  type="text"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  required
                />
                <p v-if="errors.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</p>
              </div>

              <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input
                  id="email"
                  v-model="form.email"
                  type="email"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  required
                />
                <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email }}</p>
              </div>

              <div class="flex items-center gap-4">
                <button
                  type="submit"
                  :disabled="processing"
                  class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                >
                  Save
                </button>

                <Transition
                  enter-active-class="transition ease-in-out"
                  enter-from-class="opacity-0"
                  leave-active-class="transition ease-in-out"
                  leave-to-class="opacity-0"
                >
                  <p v-if="recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
                </Transition>
              </div>
            </form>
          </div>
        </div>

        <!-- Connected Accounts -->
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
          <div class="max-w-xl">
            <h2 class="text-lg font-medium text-gray-900">Connected Accounts</h2>
            <p class="mt-1 text-sm text-gray-600">
              Manage your connected social media accounts.
            </p>

            <div class="mt-6 space-y-4">
              <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                <div class="flex items-center">
                  <svg class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                  </svg>
                  <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900">Google / YouTube</p>
                    <p class="text-sm text-gray-500">
                      {{ $page.props.auth.user.google_id ? 'Connected' : 'Not connected' }}
                    </p>
                  </div>
                </div>
                <div>
                  <Link
                    v-if="!$page.props.auth.user.google_id"
                    :href="route('google.redirect')"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    Connect
                  </Link>
                  <span v-else class="inline-flex items-center px-3 py-2 text-sm font-medium text-green-800 bg-green-100 rounded-md">
                    ✓ Connected
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Usage Statistics -->
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
          <div class="max-w-xl">
            <h2 class="text-lg font-medium text-gray-900">Usage Statistics</h2>
            <p class="mt-1 text-sm text-gray-600">
              Track your monthly video generation usage.
            </p>

            <div class="mt-6">
              <dl class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="px-4 py-5 bg-gray-50 overflow-hidden shadow rounded-lg">
                  <dt class="text-sm font-medium text-gray-500 truncate">
                    Videos Generated This Month
                  </dt>
                  <dd class="mt-1 text-3xl font-semibold text-gray-900">
                    {{ $page.props.auth.user.videos_generated_this_month || 0 }}
                  </dd>
                </div>

                <div class="px-4 py-5 bg-gray-50 overflow-hidden shadow rounded-lg">
                  <dt class="text-sm font-medium text-gray-500 truncate">
                    Monthly Quota
                  </dt>
                  <dd class="mt-1 text-3xl font-semibold text-gray-900">
                    {{ $page.props.auth.user.monthly_video_quota || 10 }}
                  </dd>
                </div>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { useForm, usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  mustVerifyEmail: Boolean,
  status: String
})

const page = usePage()

const { data: form, patch, errors, processing, recentlySuccessful } = useForm({
  name: page.props.auth.user.name,
  email: page.props.auth.user.email
})

const updateProfile = () => {
  patch(route('profile.update'))
}
</script>
