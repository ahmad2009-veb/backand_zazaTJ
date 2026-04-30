import { createApp } from 'vue';
import mitt from 'mitt';
import Pos from './pos/Pos.vue';

const emitter = mitt();

const app = createApp(Pos);
app.config.globalProperties.$emitter = emitter;
app.mount('#app');
