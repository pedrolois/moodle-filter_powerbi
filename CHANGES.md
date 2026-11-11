# Changelog

## 0.2.2 (2026-11-11)

- Report height set with a CSS variable, so the full screen view needs no `!important`.

## 0.2.1 (2026-11-11)

- "Save and test the connection" button in the settings: saves first, then tests.
- Uses Moodle's trusted certificate bundle for Microsoft calls, with a clear message when the server
  has none (cURL error 60).

## 0.2.0 (2026-11-11)

- Filters by course category, the viewer's groups, cohorts and profile fields (standard and custom).
- A viewer in several groups or cohorts sees the data of all of them; a viewer who matches none sees
  no data.
- Full screen button under every report.
- Embedded reports follow the language of the Moodle user.

## 0.1.0 (2026-11-11)

- First version: Power BI reports in content, with the secure embed and the embedded mode (app
  registration, embed tokens, row-level security), filter by course or user, signed references.
