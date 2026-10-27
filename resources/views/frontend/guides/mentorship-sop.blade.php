@extends('frontend.guides.layout')
@section('title', 'Mentorship SOP')
@section('description', 'Step-by-step guide: create a mentorship, add modules, invite mentees, and confirm attendance.')

@section('body')
<header class="hero"><div class="wrap">
  <span class="tag">Standard Operating Procedure</span>
  <h1>Running a Mentorship,<br>from Start to Finish</h1>
  <p>A plain-language guide to creating a mentorship, adding modules, inviting mentees, and making sure everyone confirms their attendance.</p>
  <div class="meta">
    <span class="chip">👩‍⚕️ For: Mentors &amp; Mentor Leads</span>
    <span class="chip">🧑‍🎓 Also useful for: Mentees</span>
    <span class="chip">⏱ About 15 minutes to read</span>
  </div>
</div></header>

<div class="wrap">
  <div class="journey"><ol>
    <li><a href="#create">Create the mentorship</a></li>
    <li><a href="#modules">Add modules</a></li>
    <li><a href="#invite">Invite mentees</a></li>
    <li><a href="#enroll">Mentee joins</a></li>
    <li><a href="#attend">Confirm attendance</a></li>
    <li><a href="#close">Review &amp; close</a></li>
  </ol></div>

  <section>
    <div class="eyebrow">The big picture</div>
    <h2>How it all fits together</h2>
    <p class="lead">Think of a mentorship like a school term. Knowing the four words below makes everything else easy.</p>
    <div class="grid">
      <div class="card"><div class="ic">🏥</div><h4>Mentorship</h4><p>The whole programme at one facility — e.g. “EmONC mentorship at Kisumu County Hospital”.</p></div>
      <div class="card"><div class="ic">👥</div><h4>Class (cohort)</h4><p>One group of mentees going through the programme together. A mentorship can have several classes.</p></div>
      <div class="card"><div class="ic">📚</div><h4>Module</h4><p>One topic taught in the class, such as Partograph Use. Each module has its own attendance link.</p></div>
      <div class="card"><div class="ic">✅</div><h4>Attendance</h4><p>A mentee confirming they were present for a module, using a link or QR code from the mentor.</p></div>
    </div>
    <div class="note info"><span class="ic">🧭</span><div><b>Before you start</b>Make sure you can sign in at the admin site and that your mentees already have (or will be given) an email address or phone number registered on the platform.</div></div>
  </section>

  <!-- 1 -->
  <section id="create">
    <div class="eyebrow">Step 1</div>
    <h2>Create the mentorship</h2>
    <p class="lead">There is more than one way to start a mentorship. They all create the same thing, so pick the one you are most comfortable with. In the guided set-up your progress is saved, so you can leave and come back.</p>
    <span class="path">Training Management <i>›</i> Mentorship Trainings <i>›</i> the button at the top of the page</span>

    <h3 style="margin:22px 0 8px">Pick your starting button</h3>
    <table>
      <tr><th>Button</th><th>How it works</th></tr>
      <tr><td><b>New Mentorship</b></td><td>One short form: run type, location, programme, dates and number of mentees. You add classes, modules and mentees afterwards.</td></tr>
      <tr><td><b>New Mentorship Guided Setup</b></td><td>A step-by-step wizard that also creates the first class, adds modules, enrolls mentees and sends invitations. Recommended for first-timers.</td></tr>
      <tr><td><b>Quick Setup</b></td><td>The same questions as the guided set-up, but all on one page in sections, for people who know what they want.</td></tr>
      <tr><td><b>Chat Setup</b></td><td>Answer questions in a chat. The assistant fills in the set-up for you as you reply.</td></tr>
      <tr><td><b>MNCHGPT</b></td><td>An AI set-up assistant with a checklist of what is still missing. It only appears when your administrator has switched it on.</td></tr>
    </table>
    <div class="note info"><span class="ic">🔘</span><div><b>Can’t see one of the buttons?</b>Administrators can switch each button on or off in <em>Mentorship Settings</em>. If a button is missing, it has been switched off — use another one, or ask your administrator.</div></div>

    <h3 style="margin:22px 0 8px">What you will be asked</h3>
    <ol class="steps">
      <li><b>Run Type</b><span>Choose <em>Live Mentorship</em> (counts in dashboards and reports) or <em>Pilot Run</em> (for practice — never counted in reports).</span></li>
      <li><b>Location</b><span>Pick the <em>County</em> first, then the <em>Facility</em> will appear. Choose where the mentorship will take place.</span></li>
      <li><b>Program &amp; Schedule</b><span>Tap a programme card (Newborn, Infant &amp; Child, or EmONC). Set the start and end dates and how many mentees (between 2 and 10).</span></li>
      <li><b>First Class</b><span>Give the first cohort a name (e.g. “March 2026 Cohort”), its dates, and an optional description.</span></li>
    </ol>
    <div class="note warn"><span class="ic">⚠️</span><div><b>Choose the programme carefully</b>The programme decides which modules are available, and it cannot be changed after saving.</div></div>
    <div class="note info"><span class="ic">📅</span><div><b>EmONC has no mentorship dates</b>For EmONC the start and end date boxes are hidden on the mentorship itself. The dates come from the <em>classes</em> you create, so set them carefully there.</div></div>
    <div class="note info"><span class="ic">🏷️</span><div><b>You don’t type a name</b>The system names the mentorship for you from the programme, the facility and the month, and gives it an ID such as <code>MT-AB12CD</code>. You name the <em>classes</em>.</div></div>
    <div class="note tip"><span class="ic">📋</span><div><b>Before you start: the preparation checklist</b>The create page reminds you to (1) identify the facility’s gaps and decide what the mentorship should address, (2) review the mentorship manuals before choosing modules and sessions, and (3) make sure every mentee has a name, email, phone, cadre and department. Links to the manuals are on the page.</div></div>
    <div class="note tip"><span class="ic">💡</span><div><b>Co-mentors come after you save</b>Working with a colleague? Open the mentorship and choose <em>Co-Mentors</em> then <em>Invite Co-Mentor</em>. Enter their email; they get an acceptance link and see the mentorship once they accept. The mentorship page shows “⑤ Co-Mentors” so you can see who is invited and who has accepted.</div></div>

    <h3 style="margin:22px 0 8px">A mentorship starts as Draft</h3>
    <p>A new <em>live</em> mentorship stays in <b>Draft</b> until it has genuinely started: at least one class has been <b>started</b> and has at least one <b>mentee enrolled</b>. Until then it cannot be made Active. Pilot runs are exempt, so you can practise freely.</p>
    <p>Mentorships that never get going — no class, nobody enrolled, or mentees but no modules — appear on <em>Training Management › Pending Mentorships</em> with a button to continue, and the assigned mentor gets an email reminder after 7 days without activity (administrators can change this in <em>Mentorship Settings</em>). Administrators also see these on <em>System Administration › Stalled Mentorships</em>.</p>
  </section>

  <!-- 2 -->
  <section id="modules">
    <div class="eyebrow">Step 2</div>
    <h2>Add modules to the class</h2>
    <p class="lead">Modules are the topics you will teach. The next screen in the guided set-up lists every module in your programme.</p>
    <ol class="steps">
      <li><b>Tick the modules you want</b><span>Choose one, several or all. You can also skip this and add them later. Ticked modules can be un-ticked to remove them.</span></li>
      <li><b>Set the dates</b><span>When you tick a module, a small box asks for its start and end date. Dates must fall within the class dates.</span></li>
      <li><b>Auto-fill sessions (optional)</b><span>Switch on “Auto-populate sessions” to copy the programme’s ready-made session plan into each module.</span></li>
    </ol>
    <div class="note info"><span class="ic">🧩</span><div><b>EmONC tip</b>Some EmONC modules (for example Postpartum Haemorrhage) are made of smaller “tracks”. Tick the whole module or only the tracks you will teach.</div></div>
  </section>

  <!-- 3 -->
  <section id="invite">
    <div class="eyebrow">Step 3</div>
    <h2>Add &amp; invite your mentees</h2>
    <p class="lead">There are three ways to get mentees into the class. Use whichever fits your group.</p>
    <div class="grid">
      <div class="card"><div class="ic">🔎</div><h4>Pick existing users</h4><p>Search by name or email and tick the people already on the platform. They are enrolled immediately.</p></div>
      <div class="card"><div class="ic">➕</div><h4>Register a new person</h4><p>Type their email, name, phone, cadre and facility. An account is created for them.</p></div>
      <div class="card"><div class="ic">📄</div><h4>Bulk upload</h4><p>For big groups, download the CSV template on the Mentees page, fill it in and upload it.</p></div>
    </div>
    <h3>Send the invitation emails</h3>
    <p>The last step of the guided set-up is <b>Send Invitations</b>. Choose who gets the email:</p>
    <table>
      <tr><th>Option</th><th>Use it when…</th></tr>
      <tr><td>All mentees with email addresses</td><td>Sending the first invitation, or a reminder to everyone.</td></tr>
      <tr><td>Only those not yet invited</td><td>You added late joiners and don’t want to email the others again.</td></tr>
    </table>
    <p>Each email contains a personal <b>enrollment link</b>. On the Mentees page, status shows <span class="pill a">Pending</span> until the mentee accepts, then <span class="pill g">Enrolled</span>. Lost email? Just resend it.</p>
  </section>

  <!-- 4 -->
  <section id="enroll">
    <div class="eyebrow">Step 4 · for mentees</div>
    <h2>How a mentee joins the class</h2>
    <p class="lead">Share this section with your mentees. It takes about two minutes.</p>
    <ol class="steps">
      <li><b>Open the invitation email</b><span>Tap the big button (or the link). No password is needed to open the page.</span></li>
      <li><b>Read the class details</b><span>You will see the class name, dates and the modules you will cover.</span></li>
      <li><b>Type your email address</b><span>Use the same email the mentor registered. If it isn’t recognised, tell your mentor so they can register you.</span></li>
      <li><b>Sign in</b><span>New to the platform? You will receive a second email to verify your account and choose a password.</span></li>
      <li><b>You’re in!</b><span>After sign-in you are enrolled automatically and land on your class progress page.</span></li>
    </ol>
    <div class="note warn"><span class="ic">⏳</span><div><b>Link not working?</b>Links stop working when the class has ended or the mentor has switched the link off. Ask your mentor for a fresh invitation.</div></div>
  </section>

  <!-- 5 -->
  <section id="attend">
    <div class="eyebrow">Step 5</div>
    <h2>Confirming attendance for each module</h2>
    <p class="lead">Every module has its own attendance link and QR code, so there is a clear record of who attended what.</p>
    <div class="duo">
      <div class="who mentor"><h4>🧑‍🏫 Mentor does</h4>
        <ul>
          <li>Open the module’s <b>Manage Mentees</b> page.</li>
          <li>Start the module — the attendance link and QR code appear.</li>
          <li>Share the QR on screen, or send the link by WhatsApp or SMS.</li>
          <li>Watch the list tick green as mentees confirm.</li>
        </ul>
      </div>
      <div class="who mentee"><h4>🧑‍🎓 Mentee does</h4>
        <ul>
          <li>Scan the QR code or tap the link.</li>
          <li><b>Already signed in?</b> Attendance is recorded instantly.</li>
          <li><b>Not signed in?</b> Type the phone number registered on your account and tap confirm.</li>
          <li>Look for the “Attendance confirmed” screen.</li>
        </ul>
      </div>
    </div>
    <div class="note tip"><span class="ic">📱</span><div><b>Phone number must match</b>If a mentee sees “Phone number not found”, the number on their account is different. The mentor can correct it, or the mentee can sign in instead.</div></div>
    <h3>Mentor controls</h3>
    <table>
      <tr><th>I want to…</th><th>Do this</th></tr>
      <tr><td>Stop people confirming</td><td><b>Deactivate Link</b>. Turn it back on any time.</td></tr>
      <tr><td>Replace a link that was shared too widely</td><td><b>Regenerate</b> — the old link and QR code stop working.</td></tr>
      <tr><td>Fix a wrong entry</td><td>Remove that mentee’s attendance from the row’s menu.</td></tr>
      <tr><td>Keep a record</td><td><b>Export attendance</b>.</td></tr>
    </table>
    <div class="note warn"><span class="ic">🔒</span><div><b>When attendance is refused</b>Attendance only works while the module is <em>in progress</em> and the link is <em>active</em>. Once a module is completed, links show “Attendance is no longer accepted”. Mentees must also be enrolled in the class.</div></div>
  </section>

  <!-- 6 -->
  <section id="close">
    <div class="eyebrow">Step 6</div>
    <h2>Review, complete and certify</h2>
    <ol class="steps">
      <li><b>Review each mentee</b><span>Check activities, post-test score and any notes from the module’s review page.</span></li>
      <li><b>Mark the module complete</b><span>Confirming attendance only shows “present”. <em>You</em> move a mentee to completed once they meet the requirements.</span></li>
      <li><b>Set the class to Completed</b><span>When all modules are done, module points are awarded to mentees.</span></li>
      <li><b>Certificate</b><span><b>Newborn and Infant &amp; Child:</b> mentees are certified as soon as the class is completed — there is no extra approval. <b>EmONC:</b> on the class’s Mentees page press <em>Mentor Approve</em> for each completed mentee (it appears once all modules are done and any video reviews are passed); the Head DRMH then gives final approval with <em>Head DRMH Certify</em>. See the <a href="{{ route('guides.emonc') }}">EmONC guide</a>. Download certificates from the <em>Certificate</em> button, or all at once as a ZIP.</span></li>
    </ol>
  </section>

  <section>
    <div class="eyebrow">Quick answers</div>
    <h2>Common questions</h2>
    <details><summary>A mentee didn’t get the invitation email.</summary><p>Ask them to check spam, then resend from the Mentees page using “Only those not yet invited”. Also confirm the email address was typed correctly.</p></details>
    <details><summary>A mentee has no email address.</summary><p>Enrol them directly from the Mentees page; they can confirm attendance with their phone number.</p></details>
    <details><summary>Can I add more modules after the class has started?</summary><p>Yes. Open the class and use Add Modules; new mentees and existing ones both receive them.</p></details>
    <details><summary>My mentorship is stuck in Draft and I can’t activate it.</summary><p>A live mentorship only becomes Active once at least one class has been <b>started</b> and has a <b>mentee enrolled</b>. Create a class, add modules, enrol at least one mentee, then start the class. Pilot runs are not held to this rule. Anything still stuck is listed under <em>Pending Mentorships</em>.</p></details>
    <details><summary>I can’t see Quick Setup, Chat Setup or MNCHGPT.</summary><p>Each of the creation buttons can be switched on or off by an administrator in <em>Mentorship Settings</em>. A missing button means it is switched off — use one of the others.</p></details>
    <details><summary>Where do I add a co-mentor?</summary><p>After saving the mentorship, open it and use <em>Co-Mentors</em> then <em>Invite Co-Mentor</em>. Co-mentors are not asked for while you create the mentorship.</p></details>
    <details><summary>What are “Pending” and “Stalled” mentorships?</summary><p>Mentorships that never got going: no class, a class with nobody enrolled, or mentees but no modules. <em>Pending Mentorships</em> (Training Management) is your list with a Continue button; administrators see everything under <em>Stalled Mentorships</em>. The assigned mentor is emailed a reminder after 7 days of no activity.</p></details>
    <details><summary>Does a pilot run show in reports?</summary><p>No. Pilot runs are excluded from every count and chart, so they are safe for practice.</p></details>
    <details><summary>The attendance QR was shared in the wrong group.</summary><p>Regenerate the link straight away. The old QR code and link stop working immediately.</p></details>
  </section>
</div>
@endsection
