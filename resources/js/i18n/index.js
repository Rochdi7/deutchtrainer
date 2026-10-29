import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import de from './de';
import en from './en';
import courseDe from './course.de';
import courseEn from './course.en';
import grammatikDe from './grammatik.de';
import grammatikEn from './grammatik.en';

const dictionaries = { de: { ...de, ...courseDe, ...grammatikDe }, en: { ...en, ...courseEn, ...grammatikEn } };

export const LOCALE_NAMES = { de: 'Deutsch', en: 'English' };

function translate(locale, key, params = {}) {
    let text = dictionaries[locale]?.[key] ?? dictionaries.de[key] ?? key;
    for (const [k, v] of Object.entries(params)) {
        text = text.split(`{${k}}`).join(v ?? '');
    }
    return text;
}

/** Composable: `const { t, locale } = useI18n()` */
export function useI18n() {
    const page = usePage();
    const locale = computed(() => page.props.locale || 'de');
    const t = (key, params) => translate(locale.value, key, params);
    const dateLocale = computed(() => (locale.value === 'de' ? 'de-DE' : 'en-GB'));

    return { t, locale, dateLocale };
}

/** Plugin: `$t('key')` and `$locale` in every template. */
export const i18n = {
    install(app) {
        app.config.globalProperties.$t = (key, params) => translate(usePage().props.locale || 'de', key, params);
    },
};
