<template>
  <AuthenticatedLayout>
    <div class="py-12">
      <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-2xl font-bold text-gray-900">{{ video.title || 'Untitled Video' }}</h2>
              <div class="mt-1 flex items-center space-x-4 text-sm text-gray-600">
                <span :class="getStatusColor(video.status)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                  {{ formatStatus(video.status) }}
                </span>
                <span>Created {{ formatDate(video.created_at) }}</span>
                <span v-if="video.duration">Duration: {{ formatDuration(video.duration) }}</span>
              </div>
            </div>
            <div class="flex items-center space-x-3">
              <Link :href="route('videos.index')" class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                ← Back to Videos
              </Link>
              <div class="flex items-center space-x-2">
                <Link
                  v-if="video.status === 'draft'"
                  :href="route('videos.edit', video.id)"
                  class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Edit
                </Link>
                <button
                  v-if="canRegenerate"
                  @click="regenerateVideo"
                  :disabled="processing"
                  class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
                >
                  <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ processing ? 'Regenerating...' : 'Regenerate' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <!-- Main Content -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Video Player -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Video Preview</h3>
                <div class="aspect-video bg-gray-900 rounded-lg flex items-center justify-center">
                  <video
                    v-if="video.video_url"
                    :src="video.video_url"
                    controls
                    class="w-full h-full rounded-lg"
                    :poster="video.thumbnail_url"
                  >
                    Your browser does not support the video tag.
                  </video>
                  <div v-else-if="video.status === 'processing'" class="text-center text-white">
                    <svg class="animate-spin h-12 w-12 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-lg font-medium">Generating your video...</p>
                    <p class="text-sm text-gray-300 mt-2">This usually takes 5-10 minutes</p>
                  </div>
                  <div v-else-if="video.status === 'failed'" class="text-center text-white">
                    <svg class="h-12 w-12 mx-auto mb-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <p class="text-lg font-medium text-red-400">Generation Failed</p>
                    <p class="text-sm text-gray-300 mt-2">{{ video.error_message || 'Something went wrong during video generation.' }}</p>
                  </div>
                  <div v-else class="text-center text-gray-400">
                    <svg class="h-12 w-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-lg font-medium">No video available</p>
                    <p class="text-sm mt-2">Video hasn't been generated yet</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Script -->
            <div v-if="video.script" class="bg-white overflow-hidden shadow-sm rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Generated Script</h3>
                <div class="bg-gray-50 rounded-lg p-4">
                  <pre class="whitespace-pre-wrap text-sm text-gray-700 font-mono">{{ video.script }}</pre>
                </div>
              </div>
            </div>

            <!-- Description -->
            <div v-if="video.description" class="bg-white overflow-hidden shadow-sm rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Description</h3>
                <div class="prose max-w-none">
                  <p class="text-gray-700 whitespace-pre-wrap">{{ video.description }}</p>
                </div>
              </div>
            </div>

            <!-- Generation Progress -->
            <div v-if="video.status === 'processing' && generations.length > 0" class="bg-white overflow-hidden shadow-sm rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Generation Progress</h3>
                <div class="space-y-3">
                  <div v-for="generation in generations" :key="generation.id" class="flex items-center">
                    <div class="flex-shrink-0">
                      <svg v-if="generation.status === 'completed'" class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                      </svg>
                      <svg v-else-if="generation.status === 'processing'" class="animate-spin h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      <svg v-else-if="generation.status === 'failed'" class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                      </svg>
                      <svg v-else class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                      </svg>
                    </div>
                    <div class="ml-3">
                      <p class="text-sm font-medium text-gray-900">{{ formatStepName(generation.step) }}</p>
                      <p v-if="generation.error_message" class="text-sm text-red-600">{{ generation.error_message }}</p>
                      <p v-else-if="generation.status === 'processing'" class="text-sm text-gray-500">In progress...</p>
                      <p v-else-if="generation.status === 'completed'" class="text-sm text-green-600">Completed</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-6">
            <!-- Video Details -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Video Details</h3>
                <dl class="space-y-3">
                  <div>
                    <dt class="text-sm font-medium text-gray-500">Topic</dt>
                    <dd class="text-sm text-gray-900">{{ video.topic }}</dd>
                  </div>
                  <div v-if="video.target_duration">
                    <dt class="text-sm font-medium text-gray-500">Target Duration</dt>
                    <dd class="text-sm text-gray-900">{{ formatDuration(video.target_duration) }}</dd>
                  </div>
                  <div v-if="video.voice_type">
                    <dt class="text-sm font-medium text-gray-500">Voice</dt>
                    <dd class="text-sm text-gray-900 capitalize">{{ video.voice_type }} ({{ video.voice_speed || 'normal' }})</dd>
                  </div>
                  <div v-if="video.keywords">
                    <dt class="text-sm font-medium text-gray-500">Keywords</dt>
                    <dd class="text-sm text-gray-900">{{ video.keywords }}</dd>
                  </div>
                  <div v-if="video.include_captions">
                    <dt class="text-sm font-medium text-gray-500">Captions</dt>
                    <dd class="text-sm text-gray-900">Included</dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- YouTube Status -->
            <div v-if="video.youtube_channel" class="bg-white overflow-hidden shadow-sm rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">YouTube Status</h3>
                <div class="space-y-3">
                  <div>
                    <dt class="text-sm font-medium text-gray-500">Channel</dt>
                    <dd class="text-sm text-gray-900">{{ video.youtube_channel.channel_name }}</dd>
                  </div>
                  <div v-if="video.youtube_video_id">
                    <dt class="text-sm font-medium text-gray-500">YouTube Video</dt>
                    <dd class="text-sm">
                      <a :href="video.youtube_url" target="_blank" class="text-indigo-600 hover:text-indigo-500">
                        View on YouTube →
                      </a>
                    </dd>
                  </div>
                  <div v-else-if="video.status === 'completed'">
                    <button
                      @click="uploadToYouTube"
                      :disabled="uploadProcessing"
                      class="w-full inline-flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50"
                    >
                      <svg v-if="uploadProcessing" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      {{ uploadProcessing ? 'Uploading...' : 'Upload to YouTube' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Actions -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Actions</h3>
                <div class="space-y-3">
                  <button
                    v-if="video.video_url"
                    @click="downloadVideo"
                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Download Video
                  </button>

                  <button
                    @click="shareVideo"
                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                    </svg>
                    Share Video
                  </button>

                  <button
                    @click="deleteVideo"
                    class="w-full inline-flex items-center justify-center px-3 py-2 border border-red-300 shadow-sm text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                  >
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Video
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  video: Object,
  generations: {
    type: Array,
    default: () => []
  }
})

const processing = ref(false)
const uploadProcessing = ref(false)

const canRegenerate = computed(() => {
  return ['failed', 'completed'].includes(props.video.status)
})

const getStatusColor = (status) => {
  const colors = {
    draft: 'bg-gray-100 text-gray-800',
    processing: 'bg-yellow-100 text-yellow-800',
    completed: 'bg-green-100 text-green-800',
    uploaded: 'bg-blue-100 text-blue-800',
    failed: 'bg-red-100 text-red-800'
  }
  return colors[status] || 'bg-gray-100 text-gray-800'
}

const formatStatus = (status) => {
  const labels = {
    draft: 'Draft',
    processing: 'Processing',
    completed: 'Completed',
    uploaded: 'Uploaded',
    failed: 'Failed'
  }
  return labels[status] || status
}

const formatDuration = (seconds) => {
  if (!seconds) return 'Unknown'

  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60

  if (minutes === 0) {
    return `${remainingSeconds}s`
  } else if (remainingSeconds === 0) {
    return `${minutes}m`
  } else {
    return `${minutes}m ${remainingSeconds}s`
  }
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const formatStepName = (step) => {
  const steps = {
    script_generation: 'Script Generation',
    voice_generation: 'Voice Generation',
    image_collection: 'Image Collection',
    video_assembly: 'Video Assembly',
    upload: 'YouTube Upload'
  }
  return steps[step] || step.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const regenerateVideo = () => {
  if (confirm('Are you sure you want to regenerate this video? This will create a new version.')) {
    processing.value = true
    router.post(route('videos.regenerate', props.video.id), {}, {
      onFinish: () => {
        processing.value = false
      }
    })
  }
}

const uploadToYouTube = () => {
  uploadProcessing.value = true
  router.post(route('videos.upload', props.video.id), {}, {
    onFinish: () => {
      uploadProcessing.value = false
    }
  })
}

const downloadVideo = () => {
  if (props.video.video_url) {
    const link = document.createElement('a')
    link.href = props.video.video_url
    link.download = `${props.video.title || 'video'}.mp4`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
  }
}

const shareVideo = () => {
  if (navigator.share && props.video.video_url) {
    navigator.share({
      title: props.video.title,
      text: props.video.description,
      url: props.video.video_url
    })
  } else {
    // Fallback: copy URL to clipboard
    navigator.clipboard.writeText(window.location.href).then(() => {
      alert('Video link copied to clipboard!')
    })
  }
}

const deleteVideo = () => {
  if (confirm('Are you sure you want to delete this video? This action cannot be undone.')) {
    router.delete(route('videos.destroy', props.video.id))
  }
}
</script>
