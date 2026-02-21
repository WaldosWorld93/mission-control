import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Sortable from 'sortablejs';
window.Sortable = Sortable;
