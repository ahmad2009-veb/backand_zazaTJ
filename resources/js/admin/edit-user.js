import { createApp } from 'vue';
import mitt from 'mitt';
import EditUser from './edit-user-address/EditUserAddress.vue';

const emitter = mitt();

const app = createApp(EditUser);
app.config.globalProperties.$emitter = emitter;
app.mount('#user-address');
