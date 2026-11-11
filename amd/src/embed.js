// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shows Power BI reports with an embed token from the site's app registration, and renews the token before it expires.
 *
 * @module     filter_powerbi/embed
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import * as pbi from 'filter_powerbi/powerbi_client';

/** @var {Object|null} Power BI service shared by every report on the page. */
let service = null;

/** @var {Number} Renew the token this many milliseconds before it expires. */
const RENEW_BEFORE = 5 * 60 * 1000;

/**
 * Ask the server for the token and settings of a report.
 *
 * @param {HTMLElement} frame
 * @returns {Promise<Object>}
 */
const getEmbed = (frame) => fetchMany([{
    methodname: 'filter_powerbi_get_embed',
    args: {contextid: Number(frame.dataset.contextid), reference: frame.dataset.reference},
}])[0];

/**
 * Renew the token shortly before it expires, for as long as the page is open.
 *
 * @param {HTMLElement} frame
 * @param {Object} report Embedded report.
 * @param {Number} expiration Timestamp in seconds.
 */
const scheduleRenewal = (frame, report, expiration) => {
    const delay = Math.max(60 * 1000, expiration * 1000 - Date.now() - RENEW_BEFORE);
    window.setTimeout(async() => {
        try {
            const data = await getEmbed(frame);
            await report.setAccessToken(data.token);
            scheduleRenewal(frame, report, data.expiration);
        } catch (error) {
            window.console.error(error);
        }
    }, delay);
};

/**
 * Show one report.
 *
 * @param {HTMLElement} frame
 */
const show = async(frame) => {
    const status = frame.querySelector('[data-region="status"]');
    try {
        const data = await getEmbed(frame);
        const config = {
            type: 'report',
            id: data.reportid,
            embedUrl: data.embedurl,
            accessToken: data.token,
            tokenType: pbi.models.TokenType.Embed,
            settings: {
                panes: {
                    filters: {visible: data.filterpane},
                    pageNavigation: {visible: data.navpane},
                },
                // Menus, dates and numbers in the viewer's Moodle language.
                localeSettings: {language: data.language, formatLocale: data.language},
            },
        };
        if (data.pagename) {
            config.pageName = data.pagename;
        }
        if (data.filtertable) {
            config.filters = [{
                $schema: 'http://powerbi.com/product/schema#basic',
                target: {table: data.filtertable, column: data.filtercolumn},
                operator: 'In',
                values: data.filtervalues,
                filterType: pbi.models.FilterType.Basic,
            }];
        }
        const container = document.createElement('div');
        frame.appendChild(container);
        const report = service.embed(container, config);
        report.on('loaded', () => status?.remove());
        report.on('error', (event) => {
            if (status) {
                status.textContent = event.detail?.message || event.detail?.detailedMessage || '';
            }
        });
        scheduleRenewal(frame, report, data.expiration);
    } catch (error) {
        if (status) {
            status.textContent = error.message || String(error);
            status.classList.add('text-danger');
        }
    }
};

/**
 * Show every report waiting on the page.
 */
export const init = () => {
    if (!service) {
        service = new pbi.service.Service(pbi.factories.hpmFactory, pbi.factories.wpmpFactory, pbi.factories.routerFactory);
    }
    document.querySelectorAll('[data-region="filter-powerbi"] [data-embed="1"]:not([data-started])').forEach((frame) => {
        frame.dataset.started = '1';
        show(frame);
    });
};
