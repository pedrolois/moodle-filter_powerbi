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
 * Full screen button of Power BI reports.
 *
 * @module     filter_powerbi/fullscreen
 * @copyright  2026 Pedro Lois {@link https://github.com/pedrolois}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

let listening = false;

/**
 * Show a report full screen when its button is clicked.
 */
export const init = () => {
    if (listening) {
        return;
    }
    listening = true;
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action="filter-powerbi-fullscreen"]');
        if (!button) {
            return;
        }
        const frame = button.closest('[data-region="filter-powerbi"]')?.querySelector('.filter-powerbi-frame');
        if (frame?.requestFullscreen) {
            frame.requestFullscreen().catch(() => {
                // The browser refused (for example inside a frame that does not allow it): nothing to do.
            });
        }
    });
};
