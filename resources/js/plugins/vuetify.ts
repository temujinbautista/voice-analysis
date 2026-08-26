import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';

import { createVuetify } from 'vuetify';

export default createVuetify({
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                colors: {
                    primary: '#1867C0',
                    background: '#ECF4FD',
                },
            },
        },
    },
    icons: {
        defaultSet: 'mdi',
    },
});
