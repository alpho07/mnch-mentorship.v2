import { chromium } from '@playwright/test';

const BASE = 'http://localhost:8000';

async function login(page, email, password) {
  await page.goto(`${BASE}/admin/login`);
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(2000); // wait for Livewire to hydrate

  // Try multiple selectors for Filament's rendered email field
  const emailSelectors = [
    'input[type="email"]',
    'input[id*="email"]',
    'input[name*="email"]',
    '[wire\\:model*="email"]',
    '.fi-input[type="email"]',
  ];
  let emailFilled = false;
  for (const sel of emailSelectors) {
    try {
      const el = page.locator(sel).first();
      if (await el.isVisible({ timeout: 3000 })) {
        await el.fill(email);
        emailFilled = true;
        console.log(`  → Used email selector: ${sel}`);
        break;
      }
    } catch {}
  }
  if (!emailFilled) {
    // fallback: take snapshot to debug
    await page.screenshot({ path: '/tmp/login_debug.png' });
    throw new Error('Could not find email input. Check /tmp/login_debug.png');
  }

  // Password field
  await page.locator('input[type="password"]').first().fill(password);

  // Submit
  await page.locator('button[type="submit"], .auth-btn').first().click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  const url = page.url();
  console.log(`  → Logged in, redirected to: ${url}`);
  return url;
}

async function run() {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } });
  const page = await ctx.newPage();

  console.log('\n═══════════════════════════════════');
  console.log('  EMONC MENTOR + MENTEE FULL TEST');
  console.log('═══════════════════════════════════\n');

  // ── 1. MENTOR LOGIN ──────────────────────────────────────────────────────
  console.log('1. Logging in as MENTOR (super@admin.com)...');
  await login(page, 'super@admin.com', 'password');
  const afterLogin = page.url();
  if (!afterLogin.includes('/admin')) {
    console.log('  ✗ FAIL: did not land on admin panel');
  } else {
    console.log('  ✓ Admin panel loaded');
  }

  // ── 2. Go to Review Module Mentee page ──────────────────────────────────
  console.log('\n2. Navigating to ReviewModuleMentee (Track 1, participant 247)...');
  const reviewUrl = `${BASE}/admin/mentorship-trainings/242/classes/233/modules/271/participants/247/review`;
  await page.goto(reviewUrl);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  const rubricHeading = await page.locator('text=Practical Assessment').first().isVisible().catch(() => false);
  const conductBtn = await page.locator('text=Conduct Practical Assessment').first().isVisible().catch(() => false);
  const rubricTitleText = await page.locator('text=Bimanual Compression').first().isVisible().catch(() => false);
  const notAssessed = await page.locator('text=Not Yet Assessed').first().isVisible().catch(() => false);

  console.log(`  ${rubricHeading ? '✓' : '✗'} "Practical Assessment" section visible`);
  console.log(`  ${conductBtn ? '✓' : '✗'} "Conduct Practical Assessment" button visible`);
  console.log(`  ${rubricTitleText ? '✓' : '✗'} Rubric title "Bimanual Compression" visible`);
  console.log(`  ${notAssessed ? '✓' : '✗'} "Not Yet Assessed" badge visible (expected - no assessment yet)`);

  await page.screenshot({ path: '/tmp/emonc_02_review_page.png', fullPage: true });
  console.log('  ✓ Screenshot: /tmp/emonc_02_review_page.png');

  // ── 3. Navigate to Conduct Assessment ───────────────────────────────────
  console.log('\n3. Navigating to Conduct Practical Assessment...');
  const conductHref = await page.locator('a[href*="rubric-assessments/create"]').first().getAttribute('href').catch(() => null);
  console.log(`  → Conduct URL from page: ${conductHref}`);

  const conductFullUrl = conductHref ? `${BASE}${conductHref}` : `${BASE}/admin/rubric-assessments/create?rubric_id=3&mentee_id=248`;
  await page.goto(conductFullUrl);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);

  const step1Heading = await page.locator('text=Assessment Setup').first().isVisible().catch(() => false);
  console.log(`  ${step1Heading ? '✓' : '✗'} Step 1 "Assessment Setup" visible`);
  await page.screenshot({ path: '/tmp/emonc_03_conduct_step1.png', fullPage: false });

  // ── 4. Fill Step 1 ───────────────────────────────────────────────────────
  console.log('\n4. Filling Step 1: selecting rubric and mentee...');

  // Get all selects
  const allSelects = page.locator('select.ra-select');
  const selectCount = await allSelects.count();
  console.log(`  → Found ${selectCount} select elements`);

  // Select rubric (first select)
  if (selectCount >= 1) {
    const rubricSel = allSelects.nth(0);
    const opts = await rubricSel.locator('option').allTextContents();
    console.log(`  → Rubric options: ${opts.slice(0,4).join(' | ')}...`);
    // Find bimanual option
    const bimanualOpt = opts.find(o => o.toLowerCase().includes('bimanual'));
    if (bimanualOpt) {
      await rubricSel.selectOption({ label: bimanualOpt });
      console.log(`  ✓ Selected rubric: ${bimanualOpt}`);
    } else {
      await rubricSel.selectOption({ index: 1 });
      console.log(`  ✓ Selected first rubric option`);
    }
  }

  // Select mentee (second select)
  if (selectCount >= 2) {
    const menteeSel = allSelects.nth(1);
    const opts = await menteeSel.locator('option').allTextContents();
    const franciscaOpt = opts.find(o => o.toLowerCase().includes('francisca') || o.toLowerCase().includes('mumbi'));
    if (franciscaOpt) {
      await menteeSel.selectOption({ label: franciscaOpt });
      console.log(`  ✓ Selected mentee: ${franciscaOpt}`);
    } else {
      await menteeSel.selectOption({ index: 1 });
      console.log(`  ✓ Selected first mentee option`);
    }
  }

  await page.screenshot({ path: '/tmp/emonc_03b_step1_filled.png', fullPage: false });

  // Click Continue to Scoring
  await page.locator('button', { hasText: /Continue to Scoring/ }).click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  const step2Visible = await page.locator('text=items performed to standard').first().isVisible().catch(() => false);
  const caseSectionVisible = await page.locator('text=Case Scenario').first().isVisible().catch(() => false);
  console.log(`  ${step2Visible ? '✓' : '✗'} Step 2 scoring view loaded`);
  console.log(`  ${caseSectionVisible ? '✓' : '✗'} Case Scenario section visible`);

  await page.screenshot({ path: '/tmp/emonc_04_conduct_step2.png', fullPage: true });
  console.log('  ✓ Screenshot: /tmp/emonc_04_conduct_step2.png');

  // ── 5. Toggle items ───────────────────────────────────────────────────────
  console.log('\n5. Toggling rubric items (marking 10 of 12 done)...');
  const items = page.locator('.ra-item');
  const itemCount = await items.count();
  console.log(`  → Found ${itemCount} rubric items`);

  for (let i = 0; i < Math.min(10, itemCount); i++) {
    await items.nth(i).click();
    await page.waitForTimeout(150);
  }

  const scoreText = await page.locator('.ra-score-num').first().textContent().catch(() => '?');
  const passBadge = await page.locator('.ra-badge-pass').first().isVisible().catch(() => false);
  console.log(`  → Score display: ${scoreText?.trim()}`);
  console.log(`  ${passBadge ? '✓' : '✗'} PASS badge visible (10 ≥ 10 pass mark)`);

  await page.screenshot({ path: '/tmp/emonc_05_scoring_filled.png', fullPage: true });
  console.log('  ✓ Screenshot: /tmp/emonc_05_scoring_filled.png');

  // ── 6. Save assessment ────────────────────────────────────────────────────
  console.log('\n6. Saving assessment...');
  const saveBtn = page.locator('button', { hasText: /Save Assessment/ });
  const saveBtnText = await saveBtn.textContent().catch(() => '');
  console.log(`  → Save button text: ${saveBtnText?.trim()}`);
  await saveBtn.click();
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);
  const afterSaveUrl = page.url();
  console.log(`  → After save URL: ${afterSaveUrl}`);
  console.log(`  ${afterSaveUrl.includes('rubric-assessments') ? '✓' : '✗'} Redirected to assessments list`);

  await page.screenshot({ path: '/tmp/emonc_06_after_save.png', fullPage: true });
  console.log('  ✓ Screenshot: /tmp/emonc_06_after_save.png');

  // ── 7. Check review page now shows assessment ─────────────────────────────
  console.log('\n7. Returning to review page — checking assessment now shows...');
  await page.goto(reviewUrl);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  const scoreOnReview = await page.locator('text=/10.*\\/.*12/').first().isVisible().catch(() => false);
  const passOnReview = await page.locator('text=PASS').first().isVisible().catch(() => false);
  const editBtnOnReview = await page.locator('text=Edit Score').first().isVisible().catch(() => false);
  const viewDetailsBtn = await page.locator('text=View Details').first().isVisible().catch(() => false);
  const newAssessmentBtn = await page.locator('text=New Assessment').first().isVisible().catch(() => false);

  console.log(`  ${scoreOnReview ? '✓' : '✗'} Score "10/12" visible on review page`);
  console.log(`  ${passOnReview ? '✓' : '✗'} "PASS" badge visible`);
  console.log(`  ${editBtnOnReview ? '✓' : '✗'} "Edit Score" button visible`);
  console.log(`  ${viewDetailsBtn ? '✓' : '✗'} "View Details" button visible`);
  console.log(`  ${newAssessmentBtn ? '✓' : '✗'} "New Assessment" button visible`);

  await page.screenshot({ path: '/tmp/emonc_07_review_with_assessment.png', fullPage: true });
  console.log('  ✓ Screenshot: /tmp/emonc_07_review_with_assessment.png');

  // ── 8. Test Edit Assessment ───────────────────────────────────────────────
  console.log('\n8. Testing Edit Assessment flow...');
  if (editBtnOnReview) {
    await page.locator('text=Edit Score').first().click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    const editHeading = await page.locator('text=Editing Assessment').first().isVisible().catch(() => false);
    const preFilledItems = await page.locator('.era-item.done').count();
    const editScoreBar = await page.locator('.era-score-bar').first().isVisible().catch(() => false);

    console.log(`  ${editHeading ? '✓' : '✗'} "Editing Assessment" hero visible`);
    console.log(`  → Pre-ticked items: ${preFilledItems} (expected: 10)`);
    console.log(`  ${editScoreBar ? '✓' : '✗'} Live score bar visible`);

    // Untick item 1, re-tick item 11 (net same score)
    const doneItems = page.locator('.era-item.done');
    if (await doneItems.count() > 0) {
      await doneItems.first().click();
      await page.waitForTimeout(200);
    }
    const undoneItems = page.locator('.era-item:not(.era-item.done)');
    if (await undoneItems.count() > 0) {
      await undoneItems.last().click();
      await page.waitForTimeout(200);
    }

    await page.screenshot({ path: '/tmp/emonc_08_edit_page.png', fullPage: true });
    console.log('  ✓ Screenshot: /tmp/emonc_08_edit_page.png');

    const updateBtn = page.locator('button', { hasText: /Update Assessment/ });
    if (await updateBtn.isVisible()) {
      await updateBtn.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
      const afterEditUrl = page.url();
      console.log(`  → After edit URL: ${afterEditUrl}`);
      console.log(`  ${afterEditUrl.includes('/admin/rubric-assessments/') ? '✓' : '✗'} Redirected to view page`);
    }
  } else {
    console.log('  ⚠ Edit Score button not found — skipping edit test');
  }

  // ── 9. View assessment detail page ───────────────────────────────────────
  console.log('\n9. Checking View Assessment page...');
  const viewUrl = page.url();
  if (viewUrl.includes('rubric-assessments/') && !viewUrl.includes('edit') && !viewUrl.includes('create')) {
    const viewScore = await page.locator('text=/10.*\\/.*12/').first().isVisible().catch(() => false);
    const viewItems = await page.locator('.rv-item').count();
    const editHeaderBtn = await page.locator('text=Edit Assessment').first().isVisible().catch(() => false);

    console.log(`  ${viewScore ? '✓' : '✗'} Score visible on view page`);
    console.log(`  → ${viewItems} item rows in item-by-item results`);
    console.log(`  ${editHeaderBtn ? '✓' : '✗'} "Edit Assessment" header button visible`);
    await page.screenshot({ path: '/tmp/emonc_09_view_assessment.png', fullPage: true });
    console.log('  ✓ Screenshot: /tmp/emonc_09_view_assessment.png');
  }

  // ── 10. Switch to MENTEE ─────────────────────────────────────────────────
  console.log('\n10. Logging out and logging in as MENTEE...');
  await page.goto(`${BASE}/admin/logout`);
  await page.waitForLoadState('networkidle');
  await login(page, 'test-mentee@example.com', 'password');

  // ── 11. Mentee module detail ──────────────────────────────────────────────
  console.log('\n11. Navigating to Track 1 module detail as mentee...');
  await page.goto(`${BASE}/mentee/class/233/module/271`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  const moduleTitle = await page.locator('h1, h2').first().textContent().catch(() => '');
  console.log(`  → Module heading: ${moduleTitle?.trim()}`);

  const practicalSection = await page.locator('text=Practical Assessment').first().isVisible().catch(() => false);
  const scoreOnMentee = await page.locator('text=/10.*\\/.*12/').first().isVisible().catch(() => false);
  const passOnMentee = await page.locator('text=PASS').first().isVisible().catch(() => false);
  const viewFullLink = await page.locator('text=View full assessment').first().isVisible().catch(() => false);
  const assessorName = await page.locator('text=Super Admin').first().isVisible().catch(() => false);

  console.log(`  ${practicalSection ? '✓' : '✗'} "Practical Assessment" section visible`);
  console.log(`  ${scoreOnMentee ? '✓' : '✗'} Score "10/12" visible to mentee`);
  console.log(`  ${passOnMentee ? '✓' : '✗'} "PASS" badge visible to mentee`);
  console.log(`  ${viewFullLink ? '✓' : '✗'} "View full assessment →" link visible`);
  console.log(`  ${assessorName ? '✓' : '✗'} Assessor name visible`);

  await page.screenshot({ path: '/tmp/emonc_10_mentee_module_detail.png', fullPage: true });
  console.log('  ✓ Screenshot: /tmp/emonc_10_mentee_module_detail.png');

  // ── 12. Curriculum Rubric Management ─────────────────────────────────────
  console.log('\n12. Testing Curriculum Rubric Management (as admin)...');
  await page.goto(`${BASE}/admin/logout`);
  await page.waitForLoadState('networkidle');
  await login(page, 'super@admin.com', 'password');

  await page.goto(`${BASE}/admin/rubric-managements`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1500);

  const rubricMgmtHeader = await page.locator('h1, h2').first().textContent().catch(() => '');
  const rubricRowCount = await page.locator('table tbody tr').count().catch(() => 0);
  console.log(`  → Page heading: ${rubricMgmtHeader?.trim()}`);
  console.log(`  ${rubricRowCount >= 12 ? '✓' : '✗'} All 12 rubrics shown (found: ${rubricRowCount})`);

  await page.screenshot({ path: '/tmp/emonc_11_rubric_management.png', fullPage: true });
  console.log('  ✓ Screenshot: /tmp/emonc_11_rubric_management.png');

  // ── 13. Edit a rubric ────────────────────────────────────────────────────
  console.log('\n13. Opening Edit Rubric (Bimanual, ID 3)...');
  await page.goto(`${BASE}/admin/rubric-managements/3/edit`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(2000);

  const bimanualInForm = await page.locator('input[value*="Bimanual"], textarea:has-text("Bimanual")').first().isVisible().catch(() => false);
  const repeaterSection = await page.locator('text=Checklist Items').first().isVisible().catch(() => false);
  const caseScenarioSection = await page.locator('text=Case Scenario').first().isVisible().catch(() => false);

  console.log(`  ${bimanualInForm ? '✓' : '✗'} Rubric title pre-filled`);
  console.log(`  ${repeaterSection ? '✓' : '✗'} "Checklist Items" repeater section visible`);
  console.log(`  ${caseScenarioSection ? '✓' : '✗'} "Case Scenario" section visible`);

  await page.screenshot({ path: '/tmp/emonc_12_rubric_edit.png', fullPage: true });
  console.log('  ✓ Screenshot: /tmp/emonc_12_rubric_edit.png');

  await browser.close();

  console.log('\n═══════════════════════════════════');
  console.log('  ALL TESTS COMPLETE');
  console.log('═══════════════════════════════════\n');
}

run().catch(e => { console.error('\nTEST CRASHED:', e.message, '\n', e.stack); process.exit(1); });
