# Graph Report - C:/xampp/htdocs/MNCH-System/public/m-assessment-app  (2026-05-23)

## Corpus Check
- 60 files · ~81,990 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 376 nodes · 650 edges · 24 communities (21 shown, 3 thin omitted)
- Extraction: 99% EXTRACTED · 1% INFERRED · 0% AMBIGUOUS · INFERRED: 8 edges (avg confidence: 0.89)
- Token cost: 2,800 input · 950 output

## Community Hubs (Navigation)
- [[_COMMUNITY_Shared UI Components|Shared UI Components]]
- [[_COMMUNITY_AI Chatbot Widget|AI Chatbot Widget]]
- [[_COMMUNITY_Package Dependencies|Package Dependencies]]
- [[_COMMUNITY_Base UI Components|Base UI Components]]
- [[_COMMUNITY_Training & Resources View|Training & Resources View]]
- [[_COMMUNITY_Navigation & Color System|Navigation & Color System]]
- [[_COMMUNITY_Offline Sync Engine|Offline Sync Engine]]
- [[_COMMUNITY_API Service Layer|API Service Layer]]
- [[_COMMUNITY_App Shell & PWA Install|App Shell & PWA Install]]
- [[_COMMUNITY_Network & Offline Store|Network & Offline Store]]
- [[_COMMUNITY_App Branding & Config|App Branding & Config]]
- [[_COMMUNITY_Mentorship Home & Navigation|Mentorship Home & Navigation]]
- [[_COMMUNITY_Classes & Role Management|Classes & Role Management]]
- [[_COMMUNITY_Mentorship Reports & Charts|Mentorship Reports & Charts]]
- [[_COMMUNITY_Mentorship Form Screen|Mentorship Form Screen]]
- [[_COMMUNITY_Class Form & Confirmation|Class Form & Confirmation]]
- [[_COMMUNITY_Class Detail & Progress|Class Detail & Progress]]
- [[_COMMUNITY_Mentee Manager|Mentee Manager]]
- [[_COMMUNITY_New Assessment Flow|New Assessment Flow]]
- [[_COMMUNITY_Mentorships List|Mentorships List]]
- [[_COMMUNITY_Module Detail & Sessions|Module Detail & Sessions]]
- [[_COMMUNITY_Session Notes Screen|Session Notes Screen]]

## God Nodes (most connected - your core abstractions)
1. `T` - 36 edges
2. `calcGrade()` - 14 edges
3. `flush()` - 8 edges
4. `GRADE_COLOR` - 7 edges
5. `GRADE_BG` - 7 edges
6. `BackButton()` - 7 edges
7. `ProgressBar()` - 7 edges
8. `openDB()` - 7 edges
9. `GradeBadge()` - 6 edges
10. `request()` - 6 edges

## Surprising Connections (you probably didn't know these)
- `MNCH App Apple Touch Icon (180px)` --conceptually_related_to--> `Capacitor Mobile Wrapper (Android/iOS)`  [INFERRED]
  m-assessment-app/public/apple-touch-icon.png → m-assessment-app/index.html
- `Progressive Web App (PWA) Support` --conceptually_related_to--> `Capacitor Mobile Wrapper (Android/iOS)`  [INFERRED]
  m-assessment-app/public/icon-192.png → m-assessment-app/index.html
- `MNCH Assessments HTML Entry Point` --references--> `Vite Build Tool Logo SVG`  [EXTRACTED]
  m-assessment-app/index.html → m-assessment-app/public/vite.svg
- `MNCH App Apple Touch Icon (180px)` --semantically_similar_to--> `MNCH App PWA Icon 192px`  [INFERRED] [semantically similar]
  m-assessment-app/public/apple-touch-icon.png → m-assessment-app/public/icon-192.png
- `MNCH App PWA Icon 192px` --semantically_similar_to--> `MNCH App PWA Icon 512px`  [INFERRED] [semantically similar]
  m-assessment-app/public/icon-192.png → m-assessment-app/public/icon-512.png

## Hyperedges (group relationships)
- **MNCH Assessment App Technology Stack** — index_html_mnch_assessments_app, concept_react_framework, concept_vite_bundler, concept_capacitor_mobile [INFERRED 0.85]
- **MNCH App Icon and Branding Assets** — icon_apple_touch, icon_192, icon_512, concept_mnch_branding [EXTRACTED 0.95]
- **PWA and Mobile Deployment Support** — icon_192, icon_512, icon_apple_touch, concept_pwa_support, concept_capacitor_mobile [INFERRED 0.75]

## Communities (24 total, 3 thin omitted)

### Community 0 - "Shared UI Components"
Cohesion: 0.07
Nodes (29): GradeBadge(), ProgressBar(), StatusChip(), enrichAssessment(), SCORED, TABS, AssessmentDetailScreen(), calcOverallFromSections() (+21 more)

### Community 1 - "AI Chatbot Widget"
Cohesion: 0.08
Nodes (14): ChatbotPanel(), MortalityThreeMonthInput(), QuestionCard(), AssessmentFormScreen(), EXPLAIN_ON_NO_SECTIONS, HR_FIELDS, ProgressHeader(), RecommendationsPanel() (+6 more)

### Community 2 - "Package Dependencies"
Cohesion: 0.07
Nodes (29): dependencies, @capacitor/android, @capacitor/cli, @capacitor/core, @jcesarmobile/ssl-skip, react, react-dom, devDependencies (+21 more)

### Community 3 - "Base UI Components"
Cohesion: 0.08
Nodes (13): Avatar(), BackButton(), GRADE_BG, GRADE_COLOR, GRADE_LABEL, GRADE_TEXT, NavIcons, STATUS_STYLE (+5 more)

### Community 4 - "Training & Resources View"
Cohesion: 0.08
Nodes (14): ResourceCard(), TYPE_COLORS, TABS, TrainingsScope(), AnalyticsHomeScreen(), CLASS_STATUS, inputStyle, MentorshipDetailScreen() (+6 more)

### Community 5 - "Navigation & Color System"
Cohesion: 0.09
Nodes (11): COLORS, PATHS, SectionIcon(), KENYA_MAP, AssessmentAnalyticsHomeScreen(), BarRow(), C, fmt() (+3 more)

### Community 6 - "Offline Sync Engine"
Cohesion: 0.17
Nodes (15): STATUS_CONFIG, assertNoTempIds(), enqueue(), executeOp(), flush(), handleOffline(), handleOnline(), init() (+7 more)

### Community 7 - "API Service Layer"
Cohesion: 0.19
Nodes (15): api, del(), get(), mergeById(), patch(), post(), put(), _rawApi (+7 more)

### Community 8 - "App Shell & PWA Install"
Cohesion: 0.14
Nodes (10): InstallPrompt(), headerGradient(), SCOPE_COMPONENTS, ScopeShell(), AssessmentsScope(), MentorshipsScope(), LoginScreen(), cacheScopeConfig() (+2 more)

### Community 9 - "Network & Offline Store"
Cohesion: 0.15
Nodes (13): listeners, networkStatus, dbClear(), dbDelete(), dbGet(), dbGetAll(), dbGetAllKeys(), dbPut() (+5 more)

### Community 10 - "App Branding & Config"
Cohesion: 0.20
Nodes (15): Capacitor Mobile Wrapper (Android/iOS), ESLint Linting Rules, HMR Fast Refresh Development Feature, MNCH App Branding (Teal M Logo), Progressive Web App (PWA) Support, React JavaScript Framework, Vite Build Tool / Bundler, MNCH App PWA Icon 192px (+7 more)

### Community 11 - "Mentorship Home & Navigation"
Cohesion: 0.16
Nodes (7): MENTEE_TABS, MENTOR_TABS, STATUS_MAP, TAB_ICONS, AttendanceRosterScreen(), ClassProgressScreen(), ModulePickerScreen()

### Community 12 - "Classes & Role Management"
Cohesion: 0.20
Nodes (7): MyClassesScreen(), ADMIN_ROLES, ASSESSOR_ROLES, MENTEE_META, MENTOR_META, MENTOR_ROLES, SECTION_META

### Community 14 - "Mentorship Form Screen"
Cohesion: 0.25
Nodes (3): inputStyle, MentorshipFormScreen(), selectStyle

### Community 15 - "Class Form & Confirmation"
Cohesion: 0.29
Nodes (3): ClassFormScreen(), inputStyle, T

### Community 16 - "Class Detail & Progress"
Cohesion: 0.33
Nodes (3): ClassDetailScreen(), MODULE_PROGRESS_COLOR, STATUS_STYLE

### Community 17 - "Mentee Manager"
Cohesion: 0.47
Nodes (3): inputStyle(), MenteeManagerScreen(), SearchableDropdown()

### Community 18 - "New Assessment Flow"
Cohesion: 0.40
Nodes (3): NewAssessmentSheet(), todayStr(), TYPES

### Community 19 - "Mentorships List"
Cohesion: 0.40
Nodes (3): MentorshipsListScreen(), STATUS_MAP, STATUS_TABS

## Knowledge Gaps
- **94 isolated node(s):** `name`, `private`, `version`, `type`, `dev` (+89 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **3 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `T` connect `Class Form & Confirmation` to `Shared UI Components`, `AI Chatbot Widget`, `Base UI Components`, `Training & Resources View`, `Offline Sync Engine`, `App Shell & PWA Install`, `Mentorship Home & Navigation`, `Classes & Role Management`, `Mentorship Reports & Charts`, `Mentorship Form Screen`, `Class Detail & Progress`, `Mentee Manager`, `New Assessment Flow`, `Mentorships List`, `Module Detail & Sessions`, `Session Notes Screen`?**
  _High betweenness centrality (0.093) - this node is a cross-community bridge._
- **Why does `calcGrade()` connect `Shared UI Components` to `AI Chatbot Widget`, `Classes & Role Management`?**
  _High betweenness centrality (0.011) - this node is a cross-community bridge._
- **Why does `flush()` connect `Offline Sync Engine` to `API Service Layer`?**
  _High betweenness centrality (0.009) - this node is a cross-community bridge._
- **What connects `name`, `private`, `version` to the rest of the system?**
  _96 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `Shared UI Components` be split into smaller, more focused modules?**
  _Cohesion score 0.0660377358490566 - nodes in this community are weakly interconnected._
- **Should `AI Chatbot Widget` be split into smaller, more focused modules?**
  _Cohesion score 0.07862903225806452 - nodes in this community are weakly interconnected._
- **Should `Package Dependencies` be split into smaller, more focused modules?**
  _Cohesion score 0.06666666666666667 - nodes in this community are weakly interconnected._