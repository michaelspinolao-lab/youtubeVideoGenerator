<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <!-- Logo -->
          <div class="flex items-center">
            <h1 class="text-xl font-bold text-gray-900">YouTube Video Generator</h1>
          </div>

          <!-- Navigation Links -->
          <div class="flex items-center space-x-8">
            <Link :href="route('dashboard')" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">
              Dashboard
            </Link>
            <Link :href="route('videos.index')" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">
              Videos
            </Link>
            <Link :href="route('youtube-channels.index')" class="text-gray-700 hover:text-indigo-600 px-3 py-2 rounded-md text-sm font-medium">
              Channels
            </Link>

            <!-- User Dropdown -->
            <div class="relative">
              <div class="flex items-center space-x-3">
                <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.name" class="h-8 w-8 rounded-full">
                <div v-else class="h-8 w-8 rounded-full bg-indigo-500 flex items-center justify-center">
                  <span class="text-sm font-medium text-white">{{ user.name.charAt(0) }}</span>
                </div>
                <span class="text-sm font-medium text-gray-700">{{ user.name }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Videos</p>
              <p class="text-3xl font-bold text-gray-900">{{ stats.total_videos }}</p>
            </div>
            <div class="p-3 bg-indigo-100 rounded-full">
              <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
              </svg>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Uploaded</p>
              <p class="text-3xl font-bold text-green-600">{{ stats.uploaded_videos }}</p>
            </div>
            <div class="p-3 bg-green-100 rounded-full">
              <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Processing</p>
              <p class="text-3xl font-bold text-yellow-600">{{ stats.processing_videos }}</p>
            </div>
            <div class="p-3 bg-yellow-100 rounded-full">
              <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Videos Left</p>
              <p class="text-3xl font-bold text-indigo-600">{{ stats.videos_remaining_this_month }}</p>
            </div>
            <div class="p-3 bg-indigo-100 rounded-full">
              <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="mb-8">
        <div class="bg-white rounded-lg shadow p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
          <div class="flex flex-wrap gap-4">
            <Link :href="route('videos.create')" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
              </svg>
              Create New Video
            </Link>

            <Link :href="route('google.redirect')" v-if="youtubeChannels.length === 0" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
              <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"/>
              </svg>
              Connect YouTube Account
            </Link>
          </div>
        </div>
      </div>

      <!-- Recent Videos -->
      <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-900">Recent Videos</h2>
        </div>
        <div class="divide-y divide-gray-200">
          <div v-if="videos.data.length === 0" class="px-6 py-4 text-center text-gray-500">
            No videos yet. Create your first video to get started!
          </div>
          <div v-else v-for="video in videos.data" :key="video.id" class="px-6 py-4 hover:bg-gray-50">
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <div class="flex items-center space-x-3">
                  <div class="flex-shrink-0">
                    <div :class="[
                      'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                      video.status === 'uploaded' ? 'bg-green-100 text-green-800' :
                      video.status === 'failed' ? 'bg-red-100 text-red-800' :
                      video.status === 'ready' ? 'bg-blue-100 text-blue-800' :
                      'bg-yellow-100 text-yellow-800'
                    ]">
                      {{ video.status.charAt(0).toUpperCase() + video.status.slice(1).replace('_', ' ') }}
                    </div>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">
                      {{ video.title || video.topic }}
                    </p>
                    <p class="text-sm text-gray-500">
                      {{ video.youtube_channel?.channel_title || 'No channel selected' }}
                    </p>
                  </div>
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <span class="text-sm text-gray-500">{{ formatDate(video.created_at) }}</span>
                <Link :href="route('videos.show', video.id)" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">
                  View
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  videos: Object,
  youtubeChannels: Array,
  stats: Object,
  user: Object,
})

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString()
}
</script>
