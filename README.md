# Power BI reports for Moodle (filter_powerbi)

Show live, interactive **Power BI reports** inside any Moodle content: pages, labels, books, course
summaries, forum posts and anything else that uses the text editor.

Each report stays connected to Power BI. Readers can hover, drill into the data and change pages, and
whatever they see is always the current version of the report. The same report can also open
**filtered by the course it is placed in** or **by the person viewing it**.

This filter shows the reports. To insert them from the editor, install its companion
[**tiny_powerbi**](https://github.com/pedrolois/moodle-tiny_powerbi), which adds a *Power BI report*
button to TinyMCE.

---

## Contents

1. [How it works](#how-it-works)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Quick start: your first report in 5 steps](#quick-start-your-first-report-in-5-steps)
5. [The two ways to show a report](#the-two-ways-to-show-a-report)
6. [Filter a report by course, group, department…](#filter-a-report-by-course-group-department)
7. [Set up the embedded mode](#set-up-the-embedded-mode)
8. [Row-level security: each user sees only their data](#row-level-security-each-user-sees-only-their-data)
9. [Settings reference](#settings-reference)
10. [Security](#security)
11. [Troubleshooting](#troubleshooting)
12. [Privacy](#privacy)

---

## How it works

The editor plugin stores an ordinary link to the report, marked with the class `powerbi-embed`:

```html
<a class="powerbi-embed" href="https://app.powerbi.com/groups/me/reports/88beecca-…/ece858b2…">Enrolled learners</a>
```

When the page is displayed, this filter replaces the link with the live report. The display options
(height, filter, mode…) travel in the link itself, so:

- the content survives Moodle's HTML cleaning;
- if the filter is ever turned off, readers still get a working link to the report;
- no Power BI token or secret is ever stored in the content.

## Requirements

- Moodle **4.5** or later.
- A **Power BI** account (Power BI service, app.powerbi.com). Power BI Desktop alone is not enough:
  reports must be **published** to the Power BI service.
- For the embedded mode only: a Microsoft Entra **app registration** and a workspace on a Power BI
  or Fabric **capacity** (see [Set up the embedded mode](#set-up-the-embedded-mode)).

### Before you start: checklist

| You need | Secure embed | Embedded |
|---|---|---|
| A **work or school** Microsoft account (personal Gmail or Outlook.com accounts cannot use Power BI) | ✔ | ✔ |
| A Power BI **Pro** licence, or the free 60-day trial, to publish to workspaces | ✔ | ✔ |
| Power BI Desktop signed in with **that same account** | ✔ | ✔ |
| A **shared workspace** (not *My workspace*) in **Pro** licence mode, or on a capacity | – | ✔ |
| Someone who can create **app registrations** in Microsoft Entra ID | – | ✔ |
| A **Power BI administrator** to change two tenant settings | – | ✔ |

In large organisations the last two are usually the IT department: send them
[Set up the embedded mode](#set-up-the-embedded-mode).

### No Power BI account yet? A test tenant of your own

To try the plugin without your organisation's tenant, create a free Microsoft Entra tenant where you
are the administrator:

1. Sign up for Azure at **azure.microsoft.com/free** with a personal e-mail, in a private browser
   window so no other Microsoft account interferes. Azure asks for a card to verify your identity.
   If the free account is not offered, choose *pay as you go*: none of the steps below creates
   anything that is charged.
2. Your tenant is created (*Default Directory*, with a domain like `yourname.onmicrosoft.com`).
   In the Azure portal, go to **Users › New user › Create new user**, for example
   `pedro@yourname.onmicrosoft.com`, and give it the **Global Administrator** role.
3. Sign in to **app.powerbi.com** with that new user, change its password, and start the free
   **Power BI Pro / Premium Per User trial**. This user is also the Power BI administrator of the
   tenant.
4. Sign in to **Power BI Desktop** with the same user before publishing.

## Installation

1. Copy the plugin to `filter/powerbi` (Moodle 5.1+: `public/filter/powerbi`), or install it from
   *Site administration › Plugins › Install plugins*.
2. Visit *Site administration › Notifications* to finish the installation.
   The filter is **turned on automatically** for the whole site.
3. Install the editor button, [tiny_powerbi](https://github.com/pedrolois/moodle-tiny_powerbi).
4. Optional: review the settings in
   *Site administration › Plugins › Filters › Power BI reports*.

## Quick start: your first report in 5 steps

This uses the **secure embed**, which needs no configuration on the Moodle side.

1. **Build the report** in Power BI Desktop: *Home › Get data*, load your data, and add at least one
   visual.
2. **Publish it**: sign in to Power BI Desktop with your work account (top-right corner), then
   *Home › Publish* and choose a workspace (*My workspace* is fine to start). If *Publish* is greyed
   out, you are not signed in.
   > Use **Publish**, not the green *Share* button: *Share* uploads the `.pbix` file to OneDrive, and
   > that link cannot be embedded.
3. **Copy the report link**: when publishing finishes, choose *Open in Power BI*, and copy the address
   from the browser. It looks like
   `https://app.powerbi.com/groups/me/reports/<report id>/<page>`.
4. **Insert it in Moodle**: edit a page, click the **Power BI report** button in the editor, paste the
   link, give it a title, and choose *Insert report*.
5. **Save and display** the page. The report appears inside Moodle. The first time, each reader may
   need to click *Sign in* inside the report.

**Changed the data?** In Power BI Desktop choose *Home › Refresh*, then **publish again** to the same
workspace and choose **Replace**. Refreshing in Desktop alone does not change the published report.
Moodle always shows the published version, so nothing changes on the Moodle side.

## The two ways to show a report

| | **Secure embed** | **Embedded** |
|---|---|---|
| Who can see it | People who can open the report in Power BI | Every Moodle user who can see the content |
| Sign-in | Each reader signs in with Microsoft | None |
| Power BI licence per reader | Yes (Pro, PPU), unless the workspace is on a capacity | No |
| Setup in Moodle | None | App registration (tenant, client ID, secret) |
| Setup in Power BI | Share the report with the readers | Workspace on a capacity, service principal access |
| Row-level security per Moodle user | Uses the reader's own Microsoft identity | Yes, with the Moodle user (see below) |
| Best for | Staff in an organisation that already uses Power BI | Learners and anyone without a Power BI licence |

The site chooses a default mode (*Default mode* setting). The person inserting a report can override
it for that report.

In both modes, every report has a **Full screen** button under it. In the embedded mode, menus, dates
and numbers also follow the **language of the Moodle user** (for example Spanish, or Brazilian
Portuguese). In the secure embed they follow the reader's own Power BI language setting.

## Filter a report by course, group, department…

The same report can show only the data that matters where it is placed, or to the person viewing it.
Put one report in ten courses and each course shows its own figures; put it on a dashboard page and
each department sees its own, with nothing else to configure.

**1. Prepare the data in Power BI.** The report's data model needs a column that holds the value
Moodle sends:

| Filter the report by | Moodle sends (setting) | Example column in Power BI |
|---|---|---|
| This course | Course **short name** (or ID number, or ID) | `Courses[Short name]` |
| The course category | Category **name** (or ID number, or ID) | `Courses[Category]` |
| The viewer's groups in this course | Names (or ID numbers) of the viewer's **groups** | `Learners[Team]` |
| The viewer's cohorts | Names (or ID numbers) of the viewer's **cohorts** | `Staff[Cohort]` |
| The user viewing it | User **e-mail** (or username, or ID number) | `Learners[Email]` |
| The viewer's Department, Institution, City, Country, ID number | That **profile field** of the viewer | `Staff[Department]` |
| The viewer's *custom profile field* | Every custom user profile field of the site is offered | `Staff[Duty station]` |

**2. Choose the filter in the editor.** In the *Power BI report* dialogue, choose *Filter the report
by*, then type the **table** and **column** names exactly as they appear in Power BI (spaces included,
for example `Course Info` and `Short name`).

Moodle works out the values on the server and opens the report already filtered:

- A viewer in **several groups or cohorts** sees the data of all of them.
- A viewer who matches **no value** (for example, in no group) sees **no data**, never all of it.
- People who can see every group, such as teachers, see the whole report when it is filtered by
  groups.

> **Important:** these filters make reports easier to read, but they are **not a security boundary**.
> A determined user could remove a filter. When users must only ever see their own data, use
> [row-level security](#row-level-security-each-user-sees-only-their-data).

## Set up the embedded mode

The embedded mode lets every Moodle user see reports without signing in to Microsoft or holding a
Power BI licence. Moodle signs in to Power BI with its own identity (a *service principal*) and gives
each page a short-lived token for the report. The steps below are done once, usually by your
Microsoft 365 and Power BI administrators.

### Step 1 – Create the app registration (Microsoft Entra admin center)

1. Go to **entra.microsoft.com › Identity › Applications › App registrations › New registration**.
2. Name it, for example *Moodle Power BI*, choose *Accounts in this organizational directory only*,
   and select **Register**.
3. On the **Overview** page, copy the **Application (client) ID** and the **Directory (tenant) ID**.
4. Go to **Certificates & secrets › Client secrets › New client secret**, choose an expiry, and copy
   the secret **Value** at once: it is shown only once.
   > Copy the **Value** column, not the **Secret ID** column. The Secret ID looks like the other IDs
   > (`xxxxxxxx-xxxx-…`) but does not work as a secret. If the value is no longer visible, create a new
   > secret.

No API permissions need to be added to the app registration. Write down when the secret expires:
the embedded reports stop working on that day until a new secret is saved in Moodle.

### Step 2 – Let the app use Power BI (Power BI admin portal)

1. In Entra, create a **security group** (for example *Power BI embedding apps*) and add the app
   registration to it as a member. In a test tenant of your own you can skip the group and apply the
   settings below to *The entire organization*.
2. In **app.powerbi.com › Settings › Admin portal › Tenant settings › Developer settings**, enable:
   - **Service principals can call Fabric public APIs** (in older tenants: *Service principals can use Fabric APIs*, or *Allow service principals to use Power BI
     APIs*), applied to that security group;
   - **Embed content in apps**.

Changes to tenant settings can take up to 15 minutes to apply.

### Step 3 – Give the app access to a workspace

1. Create a **shared workspace** (*Workspaces › New workspace*) and publish the reports to it.
   *My workspace* cannot be used by a service principal.
   - Under **Advanced › License mode**, choose **Pro** (or a capacity: Fabric, Embedded or Premium).
     Do **not** choose *Premium per user*: Power BI does not issue embed tokens for those workspaces.
2. In the workspace, choose **Manage access › Add people or groups**, add the app registration (or
   its security group) and give it the **Member** or **Admin** role.
   > **Viewer** and **Contributor** are not enough: Power BI then answers *"Only folder user with
   > reshare permissions can generate embed token"*. Check the role in *Manage access* after adding
   > the app.
3. For production use, the workspace must be on a **capacity** (Microsoft Fabric F SKU, Power BI
   Embedded A SKU, or Premium). Without a capacity, Power BI only allows a limited number of embed
   tokens, which is enough for development and testing, and each report shows a grey bar saying it is
   a free trial version.

### Step 4 – Connect Moodle

1. Go to **Site administration › Plugins › Filters › Power BI reports**.
2. Fill in **Directory (tenant) ID**, **Application (client) ID** and **Client secret**.
3. Optional: set **Default mode** to *Embedded*.
4. Click **Save and test the connection**. It saves the settings, signs in to Microsoft and lists the
   workspaces and reports the app can use. If the list is empty, check Step 3. The test only reads
   lists: if reports still fail to show, check the role of the app in Step 3.

From now on, the editor dialogue also offers **Or choose a report**: pick the workspace, report and
page from lists instead of pasting links.

## Row-level security: each user sees only their data

With row-level security (RLS), the dataset itself decides which rows each user may see. Moodle tells
Power BI who is viewing, and Power BI applies the rule. This is the secure way to show personal
data.

1. In **Power BI Desktop**, open the report and go to **Modeling › Manage roles › New**.
2. Name the role, for example `Learner`, choose the table that holds the user (for example
   `Learners`), and enter the DAX rule:
   ```
   [Email] = USERPRINCIPALNAME()
   ```
   `USERPRINCIPALNAME()` returns the identity Moodle sends: the user field chosen in the settings
   (e-mail by default).
3. **Save** and **publish** the report again.
4. In Moodle, insert the report in **Embedded** mode and type the role name (`Learner`) in
   **Row-level security role**.

Each Moodle user now sees only the rows whose `Email` matches their own.

## Settings reference

*Site administration › Plugins › Filters › Power BI reports*

| Setting | What it does |
|---|---|
| Default mode | Secure embed or Embedded, for reports that do not choose a mode. |
| Directory (tenant) ID | Your Microsoft Entra tenant. Also sends secure-embed readers to the right sign-in page. |
| Application (client) ID | The app registration used by the embedded mode. |
| Client secret | A secret of that app registration. |
| Course field | Course value used by *This course*: short name, ID number or ID. |
| Category field | Category value used by *The course category*: name, ID number or ID. |
| Group field | Group value used by *The viewer's groups*: name or ID number. |
| Cohort field | Cohort value used by *The viewer's cohorts*: name or ID number. |
| User field | User value used by *The user viewing it* and by row-level security: e-mail, username or ID number. |

Who may insert reports is controlled by the editor plugin's capability, `tiny/powerbi:insert`
(managers, course creators and editing teachers by default).

## Security

- **Signed reports.** Each report placed in content is signed with a secret key of the site, together
  with the context it is shown in. Moodle only issues embed tokens for signed reports that the user
  can actually see, so nobody can request tokens for other reports by calling the web service
  directly.
- **Values worked out on the server.** The course, the user filter and the row-level security
  identity are always computed by Moodle, never taken from the browser.
- **Short-lived tokens.** Embed tokens are never stored. They are requested when the page opens and
  renewed in the background before they expire.
- **Secrets stay on the server.** The client secret is only used by Moodle to sign in to Microsoft.

## Troubleshooting

| What you see | What to check |
|---|---|
| Power BI will not let you sign up or publish with your e-mail | Personal e-mail addresses (Gmail, Outlook.com) cannot use Power BI. Use a work or school account, or [create a test tenant](#no-power-bi-account-yet-a-test-tenant-of-your-own). |
| *Publish* is greyed out in Power BI Desktop | Desktop is not signed in. Sign in (top-right corner) with the account of the tenant where the workspace is. |
| The report link opens OneDrive instead of Power BI | It was shared with the green *Share* button. Use *Home › Publish* and copy the address of the report in app.powerbi.com. |
| The report in Moodle still shows old data | The report was refreshed in Desktop but not published again. Publish it to the same workspace and choose *Replace*. |
| The link appears as plain text instead of a report | The filter is off in this course or activity (*Filters* page), or the link is not a Power BI report link. |
| *Sign in to view this report* | Secure embed: the reader must sign in once with their Microsoft account. |
| *You don't have access to this report* | Secure embed: share the report or the workspace with the reader in Power BI. |
| *This report needs the Power BI app registration…* | The report uses the embedded mode, but the tenant ID, client ID or secret is missing. |
| *The server cannot check Microsoft's security certificate…* (cURL error 60) | PHP has no up-to-date list of trusted certificates, common on Windows servers. Download [cacert.pem](https://curl.se/ca/cacert.pem), set `curl.cainfo` and `openssl.cafile` to it in `php.ini`, and restart the web server. |
| *Fill in and save these settings first* (connection test) | One of the three app registration settings is empty: paste it and click *Save and test the connection* again. |
| *Microsoft Entra refused the app registration* | Wrong tenant ID, client ID or secret, the **Secret ID** was pasted instead of the secret **Value**, or the secret has expired. |
| *Connected, but the app registration is not a member of any workspace yet* | Add the app to the workspace (Step 3). *My workspace* never appears: use a shared workspace. |
| *Power BI returned an error: … Unauthorized* or *PowerBINotAuthorizedException* | The app is not a Member or Admin of the report's workspace, the tenant setting in Step 2 is off (changes take up to 15 minutes), or the workspace is in *Premium per user* licence mode. |
| *Only folder user with reshare permissions can generate embed token* | The app has the **Viewer** or **Contributor** role in the workspace. Change it to **Member** or **Admin** (Step 3). |
| A grey bar says the report is a free trial version | The workspace is not on a capacity, so Power BI issues development tokens. Assign the workspace to a Fabric, Embedded or Premium capacity for production. |
| *Power BI returned an error* when the report uses RLS | The role name does not exist in the dataset, or the dataset has RLS and no role was given. |
| The filter by course or user shows no data | The table or column name does not match the data model exactly, or the values differ (for example short name versus full name). |

## Privacy

The filter stores no personal data. In the embedded mode, when a report filters by user or uses
row-level security, the chosen user field (e-mail by default) is sent to Microsoft Power BI to show
that user's data. In the secure embed, readers sign in to Microsoft themselves.

## Third-party libraries

- [Power BI JavaScript client](https://github.com/microsoft/PowerBI-JavaScript) (`powerbi-client`)
  2.25.0, MIT licence, © Microsoft Corporation.

## Licence

GNU GPL v3 or later.

Power BI and Microsoft Entra are trademarks of Microsoft Corporation. This plugin is not affiliated
with or endorsed by Microsoft.
