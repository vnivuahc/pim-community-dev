
const _: any = require('underscore');
const template: any = require('pim/template/i18n/flag');

const flagTemplate: any = _.template(template);

export const getFlag = (locale: string, displayLanguage: boolean = true): string => {
    if (!locale) {
        return '';
    }

    const [language, country] = locale.split('_');

    return flagTemplate({
        country,
        language,
        displayLanguage: displayLanguage
    });
};

export const getLabel = (labels: { [locale: string]: string }, locale: string, fallback: string): string => {
    return labels[locale] ? labels[locale] : `[${fallback}]`;
};

export default {
    getFlag,
    getLabel
}
