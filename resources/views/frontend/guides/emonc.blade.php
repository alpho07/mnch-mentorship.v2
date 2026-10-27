@extends('frontend.guides.layout')
@section('title', 'EmONC Guide')
@section('description', 'How EmONC mentors and mentees work through the platform, from modules to certificate.')
@section('theme', 'theme-emonc')

@section('body')
<header class="hero"><div class="wrap">
  <span class="tag">EmONC Programme</span>
  <h1>EmONC on the Platform:<br>Mentors &amp; Mentees</h1>
  <p>Emergency Obstetric and Neonatal Care is our most detailed programme. This guide shows each person’s part, from the first module to the final certificate.</p>
  <div class="meta">
    <span class="chip">13 modules</span>
    <span class="chip">🎥 Video skills check</span>
    <span class="chip">🏛 Two-step certification</span>
    <span class="chip">⭐ 3 CPD points</span>
  </div>
</div></header>

<div class="wrap">
  <div class="journey"><ol>
    <li><a href="#roles">Who does what</a></li>
    <li><a href="#mentor">Mentor’s path</a></li>
    <li><a href="#mentee">Mentee’s path</a></li>
    <li><a href="#video">Video check</a></li>
    <li><a href="#cert">Certificate</a></li>
  </ol></div>

  <section id="roles">
    <div class="eyebrow">Start here</div>
    <h2>Three people, one journey</h2>
    <p class="lead">EmONC is certified in two steps, so three roles are involved.</p>
    <div class="duo" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr))">
      <div class="who mentee"><h4>🧑‍🎓 Mentee</h4><ul><li>Joins the class</li><li>Confirms attendance each module</li><li>Does activities and tests</li><li>Uploads skills videos</li></ul></div>
      <div class="who mentor"><h4>🧑‍🏫 Mentor</h4><ul><li>Sets up the class and modules</li><li>Teaches each session</li><li>Reviews tests and videos</li><li>Approves for certification</li></ul></div>
      <div class="who head"><h4>🏛 Head DRMH</h4><ul><li>Reviews the full record</li><li>Gives the final approval (Head DRMH Certify)</li><li>Triggers the certificate</li></ul></div>
    </div>
    <div class="note info"><span class="ic">📖</span><div><b>Setting up the class?</b>Creating the mentorship, inviting mentees and attendance links work the same as every programme — see the <a href="{{ route('guides.mentorship') }}">Mentorship SOP</a>. This guide covers what is special about EmONC.</div></div>
  </section>

  <section>
    <div class="eyebrow">The curriculum</div>
    <h2>The 13 EmONC modules</h2>
    <table>
      <tr><th>#</th><th>Module</th><th>#</th><th>Module</th></tr>
      <tr><td>1</td><td>Infection Prevention &amp; Control</td><td>8</td><td>Sepsis &amp; Puerperal Infection</td></tr>
      <tr><td>2</td><td>Focused Antenatal Care</td><td>9</td><td>Newborn Resuscitation</td></tr>
      <tr><td>3</td><td>Labour &amp; Delivery (Normal)</td><td>10</td><td>Preterm &amp; Low Birth Weight</td></tr>
      <tr><td>4</td><td>Partograph Use</td><td>11</td><td>HIV in Pregnancy</td></tr>
      <tr><td>5</td><td>Postpartum Haemorrhage (PPH)</td><td>12</td><td>Nutrition in Pregnancy</td></tr>
      <tr><td>6</td><td>Pre-eclampsia &amp; Eclampsia</td><td>13</td><td>Family Planning Post-Partum</td></tr>
      <tr><td>7</td><td>Obstructed Labour &amp; Shoulder Dystocia</td><td></td><td></td></tr>
    </table>
    <div class="note warn"><span class="ic">🩸</span><div><b>Module 5 is bigger</b>PPH has 10 sub-tracks (for example uterine massage, balloon tamponade, tranexamic acid). Each has its own activities and may need its own video.</div></div>
  </section>

  <section id="mentor">
    <div class="eyebrow">For mentors</div>
    <h2>Your path through an EmONC class</h2>
    <ol class="steps">
      <li><b>Create the mentorship</b><span>Choose the <em>EmONC</em> programme card in the guided set-up (Quick Setup, Chat Setup and New Mentorship work too — see the <a href="{{ route('guides.mentorship') }}">Mentorship SOP guide</a>). EmONC mentorships have no start and end dates of their own: you set the dates on each <b>class</b>. Add the class and tick the modules or PPH tracks you will teach.</span></li>
      <li><b>Invite mentees</b><span>Send invitations in the final set-up step. Track who is Pending and who is Enrolled.</span></li>
      <li><b>Start a module</b><span>Open the module and share the attendance QR/link in the room.</span></li>
      <li><b>Teach the session</b><span>Mentees complete the activities and the post-test on their phones during or after the session.</span></li>
      <li><b>Review each mentee</b><span>Open their row. You will see pre-test (for information), post-test (must pass), activities and notes.</span></li>
      <li><b>Watch &amp; grade videos</b><span>Play the skills video in your browser, choose <span class="pill g">Pass</span> or <span class="pill a">Fail</span>, and add feedback. The mentee is notified automatically.</span></li>
      <li><b>Mark module complete</b><span>Do this when the mentee has met every requirement.</span></li>
      <li><b>Mentor Approve</b><span>After all 13 modules (and any video reviews passed), open the class’s Mentees page and press <em>Mentor Approve</em> on the mentee’s row. The Head DRMH is notified.</span></li>
    </ol>
  </section>

  <section id="mentee">
    <div class="eyebrow">For mentees</div>
    <h2>Your path as an EmONC mentee</h2>
    <ol class="steps">
      <li><b>Accept your invitation</b><span>Open the email, enter your email, sign in. You’re enrolled.</span></li>
      <li><b>Open your dashboard</b><span>Your class and every module are listed with your progress.</span></li>
      <li><b>Scan to confirm attendance</b><span>When the mentor starts a module, scan the QR code or tap the link. Signed in? It records at once. Otherwise enter your phone number.</span></li>
      <li><b>Do the pre-test</b><span>It shows what you already know. It doesn’t affect your result.</span></li>
      <li><b>Complete the activities</b><span>Tick each clinical task as you practise it.</span></li>
      <li><b>Take the post-test</b><span>You need a passing score to finish the module.</span></li>
      <li><b>Upload your video</b><span>Where required (see below), then wait for your mentor’s decision — you’ll get a notification.</span></li>
    </ol>
    <div class="note tip"><span class="ic">📲</span><div><b>Works on your phone</b>Everything on this page works in a phone browser. Keep your registered phone number handy for attendance.</div></div>
  </section>

  <section id="video">
    <div class="eyebrow">Skills check</div>
    <h2>The video submission</h2>
    <p class="lead">Video lets a mentor see you perform a skill, even if they weren’t in the room.</p>
    <div class="grid">
      <div class="card"><div class="ic">🎬</div><h4>1. Record</h4><p>Film yourself or a simulation performing the skill clearly, with the whole procedure in frame.</p></div>
      <div class="card"><div class="ic">⬆️</div><h4>2. Upload</h4><p>Add the video to the module on the platform. Use Wi-Fi for large files.</p></div>
      <div class="card"><div class="ic">👀</div><h4>3. Reviewed</h4><p>Your mentor watches and chooses Pass or Fail, with written feedback.</p></div>
      <div class="card"><div class="ic">🔁</div><h4>4. Retry if needed</h4><p>If you didn’t pass, read the feedback and upload a new video.</p></div>
    </div>
    <div class="note warn"><span class="ic">🔐</span><div><b>Patient privacy</b>Use a manikin or simulation. Never film a real patient’s face or personal details.</div></div>
  </section>

  <section id="cert">
    <div class="eyebrow">Finish line</div>
    <h2>Getting the EmONC certificate</h2>
    <ol class="steps">
      <li><b>All 13 modules complete</b><span>Including activities, post-tests and passed videos.</span></li>
      <li><b>Mentor approves</b><span>The mentor signs off your record.</span></li>
      <li><b>Head DRMH approves</b><span>The Head DRMH reviews your completion record and makes the final decision.</span></li>
      <li><b>Download your certificate</b><span>Find it on your Mentee Dashboard. It carries <b>3 CPD points</b> and a verification link.</span></li>
    </ol>
    <div class="note info"><span class="ic">ℹ️</span><div><b>Why two steps?</b>EmONC certifies high-risk clinical skills, so a second senior reviewer confirms competence before the certificate is issued. Newborn and Infant programmes need only the mentor’s step.</div></div>
  </section>

  <section>
    <div class="eyebrow">Quick answers</div>
    <h2>Common questions</h2>
    <details><summary>My attendance says “Phone number not found”.</summary><p>The number on your account differs from the one you typed. Sign in instead, or ask your mentor to correct your phone number.</p></details>
    <details><summary>I missed a module’s attendance window.</summary><p>Once a module is completed the link closes. Tell your mentor, who can record your attendance manually.</p></details>
    <details><summary>My video was marked Fail.</summary><p>Read your mentor’s notes, practise the highlighted steps, and upload a fresh video.</p></details>
    <details><summary>I finished everything but have no certificate.</summary><p>Your mentor must approve you and the Head DRMH must approve. Ask your mentor where your record is in that process.</p></details>
  </section>
</div>
@endsection
