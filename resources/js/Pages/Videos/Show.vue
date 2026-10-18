<script setup>
import {router, Link, useForm} from '@inertiajs/vue3';
import {computed,onMounted,onUnmounted} from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
const props=defineProps({video:Object,retryRequestId:String});
const retryForm=useForm({request_id:''});
function retry(){retryForm.request_id=props.retryRequestId;retryForm.post(route('videos.retry',props.video.id));}
const labels={draft:'Rascunho',generating_script:'Preparando roteiro',fetching_visuals:'Buscando imagens',generating_voiceover:'Criando narração',assembling_video:'Montando vídeo',ready:'Pronto para revisão',failed:'Falha na geração',uploaded:'Enviado',uploading:'Enviando'};
const busy=computed(()=>['generating_script','fetching_visuals','generating_voiceover','assembling_video','uploading'].includes(props.video.status));
let timer;onMounted(()=>{timer=setInterval(()=>{if(busy.value && !document.hidden)router.reload({only:['video']});},5000)});onUnmounted(()=>clearInterval(timer));
function remove(){if(confirm('Excluir este vídeo e seus arquivos?'))router.delete(route('videos.destroy',props.video.id));}
</script>
<template><AuthenticatedLayout><main class="max-w-3xl mx-auto p-6 space-y-5"><h1 class="text-2xl font-bold">{{video.title}}</h1><p role="status">{{labels[video.status] || video.status}}</p><p v-if="busy">O processamento continua mesmo se você sair desta página.</p><p v-if="video.failure_reason" class="text-red-700">{{video.failure_reason}}</p>
<div v-if="video.status==='failed'"><button @click="retry" :disabled="retryForm.processing" class="bg-indigo-700 text-white rounded px-5 py-3 disabled:opacity-50">{{retryForm.processing?'Enviando…':'Tentar novamente sem gastar outra cota'}}</button><p v-for="(error,key) in retryForm.errors" :key="key" role="alert" class="text-red-700">{{error}}</p></div>
<template v-if="['ready','uploaded'].includes(video.status)"><video controls preload="metadata" class="w-full" :src="route('videos.download',video.id)+'?preview=1'"/><a class="inline-block bg-indigo-700 text-white rounded p-3" :href="route('videos.download',video.id)">Baixar MP4</a><p>Envie o arquivo pelo YouTube Studio após revisar. Não há publicação automática nesta versão.</p></template>
<details v-if="video.generated_script"><summary>Ver roteiro</summary><p class="whitespace-pre-wrap">{{video.generated_script}}</p></details>
<button v-if="!busy" @click="remove" class="text-red-700">Excluir vídeo</button><Link class="block text-indigo-700" :href="route('videos.create')">Criar outro vídeo</Link></main></AuthenticatedLayout></template>
