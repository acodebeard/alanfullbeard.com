<?php

$placeholders = [
  'luke@tatooine.com',
  'oscar@grouch.net',
  'homer@springfieldpower.org',
  'frodo@shiremail.me',
  'darth@deathstar.gov',
  'scooby@snacks.biz',
  'zelda@hyrule.net',
  'pikachu@poke.center',
  'morty@c137.net',
  'optimus@autobots.io'
];
$mix_it_up = (count($placeholders) - 1);
$random_placeholder = mt_rand(0,$mix_it_up);
$placeholder = $placeholders[$random_placeholder];
?>
<section id="theGreatSampleForm" aria-labelledby="signupHeading">
  <div class="flex gap-medium third-party-login">
    <div>
      <img src="/assets/images/google-sign-in.webp" alt="Sign In With Google" width="250" title="Not really. This is just a demo.
Users like easy login without creating new accounts.
These should be prevalent options">
    </div>

    <div>
      <img src="/assets/images/apple-sign-in.webp" alt="Sign In With Apple" width="250" title="Not really. This is just a demo">
    </div>

    <script>
      function jk() {
        alert('Third Party Login is just an example.\nMany users prefer not to make new accounts on every site');
      }
      document.querySelector('.third-party-login')
          .addEventListener('click', e => {
            if (e.target.tagName === 'IMG') jk();
          });
    </script>
  </div>
  <h2 class="flex flex-column flex-align-middle margin-medium flex-justify-center text-center form-heading" id="signupHeading">
    <span>or</span>
    Create an Account
  </h2>

  <form aria-busy="false" aria-describedby="form-demo-note">
    <fieldset>
      <div class="form-row">
        <div class="flex-1">
          <label for="username">Pick a Username</label>
          <input type="text" name="username" id="username" placeholder="Choose a Username" required autocomplete="off" value="" autocapitalize="off" spellcheck="false">
        </div>
        <div class="username-status" aria-live="polite" aria-atomic="true"><span class="username-status__text sr-only"></span></div>
      </div>
      <script>

  const USERNAME = {
    pattern: /^[a-z0-9._-]{3,20}$/,
    reserved: new Set(['groot','admin', 'root', 'system', 'support', 'help', 'test', 'user', 'alan', 'desertnet', 'viazen', 'webmaster']),
    debounceMs: 300
  };

  let checkToken = 0;
  let debounceTimer = null;
  let acceptedValue = null;

  const input = document.getElementById('username');
  const statusBox = document.querySelector('.username-status');
  const statusText = statusBox?.querySelector('.username-status__text');

  if (input && statusBox && statusText) {
    input.addEventListener('input', () => {
      // normalize to lowercase without fighting the caret
      const cur = input.selectionStart;
      const val = input.value;
      const lower = val.toLowerCase();
      if (val !== lower) {
        input.value = lower;
        input.setSelectionRange(cur, cur);
      }

      const value = input.value.trim();

      // Empty -> reset
      if (!value) {
        cancelPending();
        acceptedValue = null;
        setStatus('empty', 'Enter a username.');
        return;
      }

      // If it matches an accepted (available) value, don't re-check
      if (acceptedValue !== null && value === acceptedValue) {
        cancelPending();
        setStatus('available', 'Available.');
        return;
      }

      // Value changed from accepted -> unlock and re-check (debounced)
      acceptedValue = null;
      scheduleCheck();
    });

    input.addEventListener('blur', () => {
      const value = input.value.trim();
      if (!value) {
        cancelPending();
        acceptedValue = null;
        setStatus('empty', 'Enter a username.');
        return;
      }
      if (acceptedValue !== null && value === acceptedValue) return; // no re-check
      runCheck(); // immediate on blur
    });

    setStatus('empty', 'Enter a username.');
  }

  // ----- helpers -----
  function setStatus(state, text) {
    const states = ['empty', 'invalid', 'checking', 'available', 'taken'];
    states.forEach(s => statusBox.classList.remove(`is-${s}`));
    statusBox.classList.add(`is-${state}`);
    statusText.textContent = text;

    if (state === 'invalid' || state === 'taken') {
      input.setAttribute('aria-invalid', 'true');
    } else {
      input.removeAttribute('aria-invalid');
    }
  }

  function scheduleCheck() {
    cancelPending();
    debounceTimer = setTimeout(() => runCheck(), USERNAME.debounceMs);
  }

  function cancelPending() {
    if (debounceTimer) {
      clearTimeout(debounceTimer);
      debounceTimer = null;
    }
    checkToken++; // invalidate any in-flight result
  }

  function runCheck() {
    cancelPending();
    const token = ++checkToken;
    const value = input.value.trim();

    // Pattern gate
    if (!USERNAME.pattern.test(value)) {
      setStatus('invalid', 'Use 3–20 chars: letters, numbers, dot, underscore, hyphen.');
      return;
    }

    // Enter checking state (adds .is-checking)
    setStatus('checking', 'Checking availability…');

    setTimeout(() => {
      if (token !== checkToken) return; // superseded
      const isTaken = USERNAME.reserved.has(value);
      if (isTaken) {
        setStatus('taken', 'That username is already taken.');
      } else {
        setStatus('available', 'Available.');
        acceptedValue = value; // lock until changed
      }
    }, 777);
  }
      </script>
    </fieldset>

    <fieldset>
      <legend class="form-offscreen">Contact Info</legend>

      <div class="form-row form-row-name">
        <div class="flex flex-wrap flex-align-middle gap-small">
          <div class="width-1-1">
            <label for="first_name">First Name</label>
            <input type="text" name="first_name" id="first_name" placeholder="Please Enter Your First Name" required>
          </div>
          <div class="width-1-1">
            <label for="last_name">Last Name</label>
            <input type="text" name="last_name" id="last_name" placeholder="Please Enter Your Last Name" required>
          </div>
        </div>
        <div class="name-status"></div>
      </div>

      <div class="form-row">
          <div class="flex-1">
            <label for="email">Email</label>
            <input type="email" name="email" id="email" placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"
              autocomplete="email" inputmode="email" spellcheck="false" required>
          </div>
          <div class="email-status"></div>
      </div>

      <div class="form-row form-row-phone">
        <div class="flex flex-wrap flex-align-middle gap-xsmall">
          <div class="form-label width-1-1 flex-none">Phone</div>
          <div class="flex gap-xsmall">
          <label class="form-offscreen" for="area_code">Area Code</label>
          <input type="tel" name="area_code" id="area_code" maxlength="3">
          <label class="form-offscreen" for="prefix">Phone Number Prefix</label>
          <input type="tel" name="prefix" id="prefix" maxlength="3">
          <label class="form-offscreen" for="suffix">Phone Number Suffix</label>
          <input type="tel" name="suffix" id="suffix" maxlength="4">
          </div>
        </div>
        <div class="phone-status"></div>
      </div>

      <div class="form-row flex-column gap-xsmall">
        <div class="form-label form-label-sub">Can we text you at this number? <i>(Carrier charges may apply)</i></div>
        <div class="flex flex-wrap flex-align-center gap-small form-labels">
          <div class="flex flex-align-center gap-small">
            <input class="form-offscreen" type="radio" name="text_permission" id="no_text" value="0" checked required>
            <label class="form-label-button" for="no_text">No, Please don't text me</label>
          </div>
          <div class="flex flex-align-center gap-small">
            <input class="form-offscreen" type="radio" name="text_permission" id="yes_text" value="1" required>
            <label class="form-label-button" for="yes_text">Yes, You can text me</label>
          </div>
        </div>
      </div>


    </fieldset>

    <fieldset>
      <legend class="form-offscreen">Password</legend>
      <div class="form-row flex-column flex-wrap gap-xsmall">
        <div class="form-label width-1-1 flex-none flex flex-align-center">Password<span id="pw1_error" class="form-error" hidden></span><p id="pw2_error" class="form-error" hidden></p></div>
        <p id="pw_requirements" class="form-offscreen">Password must be at least 8 characters.</p>

        <div class="flex flex-1 flex-align-middle gap-medium form-password-first">
          <div class="flex-1">
            <label for="password_first" class="form-offscreen">Choose a password</label>
            <input type="password" id="password_first" name="password_first" placeholder="Choose a password" minlength="8"
              maxlength="64" required autocomplete="new-password" spellcheck="false" autocapitalize="off"
              aria-describedby="pw_requirements pw1_error" data-nosave>
          </div>

          <div class="password-status"></div>
        </div>
        <div class="flex flex-1 flex-align-middle gap-medium form-password-second margin-top-xsmall">
          <div class="flex-1">
            <label for="password_second" class="form-offscreen">Retype your password</label>
            <input type="password" id="password_second" name="password_second" placeholder="Retype your password" minlength="8"
              maxlength="64" required autocomplete="new-password" spellcheck="false" autocapitalize="off"
              aria-describedby="pw2_error" data-nosave>
          </div>

          <div class="password-status"></div>
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend class="form-offscreen">Pizza Query</legend>

      <div class="form-row flex-column">
        <div class="form-label">Does pineapple belong on pizza?</div>
        <div class="flex flex-wrap flex-align-middle gap-small form-labels">
        <div>
          <input class="form-offscreen" type="radio" name="pineapple_opinion" id="pineapple_yes" value="2">
          <label class="form-label-button" for="pineapple_yes">Yes, It does</label>
        </div>
        <div>
          <input class="form-offscreen" type="radio" name="pineapple_opinion" id="pineapple_neutral" value="1">
          <label class="form-label-button" for="pineapple_neutral">No Opinion</label>
        </div>
        <div>
          <input class="form-offscreen" type="radio" name="pineapple_opinion" id="pineapple_no" value="0">
          <label class="form-label-button" for="pineapple_no">It's blasphemy.</label>
        </div>
        </div>
      </div>

    </fieldset>

    <footer class="flex flex-justify-between form-footer margin-top-large margin-bottom-large">
      <button type="button" class="btn-clear-draft" hidden>Reset</button>
      <button id="form-submit" type="submit">Create Account</button>
    </footer>
  </form>

  <aside style="padding-top: 8px;margin:0; border-top:thin solid #ddd; color: #444; max-width: 100%; ">
    <p id="form-demo-note" style="font-size: .8rem;" class="margin-top-small">This is a demo form. Nothing is sent to a server. Draft restoration may use this browser's local storage.</p>
  </aside>
  <script>
    // First + Last name validation
      const firstNameInput = document.getElementById('first_name');
      const lastNameInput = document.getElementById('last_name');
      const nameStatus = document.querySelector('.form-row-name .name-status');

      if (firstNameInput && lastNameInput && nameStatus) {
        function updateNameStatus() {
          const firstFilled = !!firstNameInput.value.trim();
          const lastFilled = !!lastNameInput.value.trim();
          const count = (firstFilled ? 1 : 0) + (lastFilled ? 1 : 0);

          // clear any prior classes
          nameStatus.classList.remove('one-valid', 'two-valid');

          if (count === 1) {
            nameStatus.classList.add('one-valid');
          } else if (count === 2) {
            nameStatus.classList.add('two-valid');
          }
        }

        // Listen for typing or blur
        firstNameInput.addEventListener('input', updateNameStatus);
        lastNameInput.addEventListener('input', updateNameStatus);
        firstNameInput.addEventListener('blur', updateNameStatus);
        lastNameInput.addEventListener('blur', updateNameStatus);

        // initialize
        updateNameStatus();
      }

  </script>
  <script>
    // Email validation
      const emailInput = document.getElementById('email');
      const emailStatus = document.querySelector('.email-status');

      if (emailInput && emailStatus) {
        function updateEmailStatus() {
          const value = emailInput.value.trim();

          // reset
          emailStatus.classList.remove('valid');

          // if non-empty and passes browser's built-in validity
          if (value && emailInput.checkValidity()) {
            emailStatus.classList.add('valid');
          }
        }

        // listen for changes
        emailInput.addEventListener('input', updateEmailStatus);
        emailInput.addEventListener('blur', updateEmailStatus);

        // initial state
        updateEmailStatus();
      }

  </script>

  <script>
    // Phone segmented input
      const phoneInputs = [
        document.getElementById('area_code'),
        document.getElementById('prefix'),
        document.getElementById('suffix')
      ];
      const phoneStatus = document.querySelector('.form-row-phone .phone-status');

      if (phoneInputs.every(Boolean) && phoneStatus) {
        phoneInputs.forEach((input, idx) => {
          input.addEventListener('input', () => {
            // digits only
            input.value = input.value.replace(/\D/g, '');

            // add/remove partial-valid class for this input
            input.classList.toggle(
              'partial-valid',
              input.value.length > 0 && input.value.length < input.maxLength
            );

            // auto-advance when max length hit
            if (input.value.length === input.maxLength && idx < phoneInputs.length - 1) {
              phoneInputs[idx + 1].focus();
            }

            updatePhoneStatus();
          });

          input.addEventListener('keydown', e => {
            // backspace on empty moves focus back
            if (e.key === 'Backspace' && input.value.length === 0 && idx > 0) {
              phoneInputs[idx - 1].focus();
            }
          });

          input.addEventListener('blur', updatePhoneStatus);
        });

        // initialize
        updatePhoneStatus();
      }

      function updatePhoneStatus() {
        const values = phoneInputs.map(i => i.value.trim());
        const totalDigits = values.join('').length;

        phoneStatus.classList.remove('partial-valid', 'valid');

        if (totalDigits === 0) return; // nothing entered yet
        if (totalDigits === 10) {
          phoneStatus.classList.add('valid');
        } else {
          phoneStatus.classList.add('partial-valid');
        }
      }
  </script>

  <script>
    // Password validation (min length + match)
  const form = document.querySelector('form');
      const pw1 = document.getElementById('password_first');
      const pw2 = document.getElementById('password_second');
    const pw2Error = document.getElementById('pw2_error');
      const pw1Status = document.querySelector('.form-password-first .password-status');
      const pw2Status = document.querySelector('.form-password-second .password-status');

      if (pw1 && pw2 && pw1Status && pw2Status) {
        function cls(el, ...names) {
          // remove any status classes starting with is- or simple flags we use
          [...el.classList].forEach(c => {
            if (c.startsWith('is-') || ['valid', 'invalid', 'mismatch', 'partial-valid'].includes(c)) {
              el.classList.remove(c);
            }
          });
          names.forEach(n => el.classList.add(n));
        }

        function updatePw1() {
          const v = pw1.value;
          if (!v) {
            cls(pw1Status, 'is-empty');
            pw1.removeAttribute('aria-invalid');
          } else if (v.length < 8) {
            cls(pw1Status, 'invalid', 'is-too-short');
            pw1.setAttribute('aria-invalid', 'true');
          } else {
            cls(pw1Status, 'valid');
            pw1.removeAttribute('aria-invalid');
          }
          // any change to pw1 can affect match status of pw2
          updatePw2();
        }

        function updatePw2() {
          const v1 = pw1.value;
          const v2 = pw2.value;

          if (!v2) {
            cls(pw2Status, 'is-empty');
            pw2.removeAttribute('aria-invalid');
            return;
          }

          if (v2.length < 8) {
            cls(pw2Status, 'invalid', 'is-too-short');
            pw2.setAttribute('aria-invalid', 'true');
            return;
          }

          if (v1 && v2 && v1 === v2 && v1.length >= 8) {
            cls(pw2Status, 'valid');
            pw2.removeAttribute('aria-invalid');
          } else {
            cls(pw2Status, 'mismatch', 'invalid');
            pw2.setAttribute('aria-invalid', 'true');
          }
        }

        // listeners
        pw1.addEventListener('input', updatePw1);
        pw1.addEventListener('blur', updatePw1);
        pw2.addEventListener('input', updatePw2);
        pw2.addEventListener('blur', updatePw2);

        // init
        updatePw1();
      }
      if (form && pw1 && pw2) {
          form.addEventListener('submit', function (e) {
            // trim to be safe
            const val1 = pw1.value.trim();
            const val2 = pw2.value.trim();

            if (val1 !== val2) {
              e.preventDefault(); // stop form submission
              pw2.setAttribute('aria-invalid', 'true');
              if (pw2Error) {
                pw2Error.textContent = 'Passwords do not match!';
                pw2Error.hidden = false;
              }
              pw2.focus();
            } else {
              pw2.removeAttribute('aria-invalid');
              if (pw2Error) {
                pw2Error.textContent = '';
                pw2Error.hidden = true;
              }
            }
          });
        }

  </script>

</section>

<script>
  (() => {
    const section = document.getElementById('theGreatSampleForm');
    if (!section) return;

    const form = section.querySelector('form');
    if (!form) return;

    // Update to your real asset path
    const PINEAPPLE_SRC = '/assets/images/pineapple.webp';

    const sel = {
      first: '#first_name',
      user: '#username',
      text: 'input[name="text_permission"]:checked',        // "0" | "1"
      pineapple: 'input[name="pineapple_opinion"]:checked'    // "2" | "1" | "0"
    };

    const esc = s => (s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    form.addEventListener('submit', (e) => {
      e.preventDefault(); // process-only; no real submit


      // add processing class
      section.classList.add('form-processing');

      // remove it after 1138ms
      setTimeout(() => {
        section.classList.remove('form-processing');
      }, 1333);

      // Block submit if username isn't confirmed available
      const usernameInput = section.querySelector('#username');
      const usernameStatus = section.querySelector('.username-status');

      const isAvailable =
        usernameStatus &&
        usernameStatus.classList.contains('is-available');

      const isChecking =
        usernameStatus &&
        usernameStatus.classList.contains('is-checking');

      const isTakenOrInvalid =
        usernameStatus &&
        (usernameStatus.classList.contains('is-taken') ||
          usernameStatus.classList.contains('is-invalid') ||
          usernameStatus.classList.contains('is-empty'));

      if (isChecking) {
        alert('Still checking username — please wait a moment.');
        usernameInput?.focus();
        return;
      }

      if (!isAvailable || isTakenOrInvalid) {
        alert('Please choose an available username.');
        usernameInput?.focus();
        return;
      }

      const first = section.querySelector(sel.first)?.value.trim() || '';
      const user = section.querySelector(sel.user)?.value.trim() || '';
      const textChoice = section.querySelector(sel.text)?.value || '';       // "0" | "1" | ""
      const fruitChoice = section.querySelector(sel.pineapple)?.value || '';  // "2" | "1" | "0" | ""

      const greetName = first || user || 'friend';
      const altHandle = user ? `Or should we say ${esc(user)}?` : '';

      // texting line
      let textingHTML = '';
      if (textChoice === '0') {
        textingHTML = `Per your request, <span class="text-negative">we won’t be texting you</span>.`;
      } else if (textChoice === '1') {
        textingHTML = `We have your permission to text. We promise no spam. You can change this at any time.`;
      }

      // pineapple line (fallback if none chosen)
      let pineappleLine = 'We understand. Some people steer clear of that whole pizza/pineapple debate.';
      if (fruitChoice === '2') pineappleLine = 'your pineapple answer was correct';
      else if (fruitChoice === '1') pineappleLine = 'your pineapple answer is wise';
      else if (fruitChoice === '0') pineappleLine = 'your pineapple answer indicates you may require two pizzas so everyone gets their favorite. or three. there is really no ceiling';

      const pineappleHTML = `
      <div class="pineapple-result flex flex-align-center gap-small">
        <div class="pineapple-img"><img width="45" src="${PINEAPPLE_SRC}" alt="Pineapple"></div>
        <p class="flex-1">${pineappleLine}</p>
      </div>`;

      const successHTML = `
      <div class="success-panel" role="region" aria-labelledby="successTitle">
        <h3 id="successTitle" tabindex="-1">Thanks, ${esc(greetName)}!</h3>
        ${altHandle ? `<p>${altHandle}</p>` : ''}
        <p>Welcome to the club.</p>

        ${textingHTML ? `<p class="texting-note">${textingHTML}</p>` : ''}

        ${pineappleHTML}

        <p class="disclaimer">This form didn’t really make an account. Nothing is sent to a server.</p>
      </div>
    `;

      section.innerHTML = successHTML;

      // focus success title
      section.querySelector('#successTitle')?.focus();

      // fake link does nothing
      section.querySelector('.fake-link-start')?.addEventListener('click', ev => {
        ev.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  })();
</script>

<script>
  // ===== Autosave / Restore (Save Draft) =====
  (() => {
    // jic css.escape isn't supported
    if (!window.CSS || !CSS.escape) {
      CSS = CSS || {};
      CSS.escape = (s) => String(s).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }

    const section = document.getElementById('theGreatSampleForm');
    if (!section) return;

    const form = section.querySelector('form');
    if (!form) return;

    const DRAFT_KEY = `sampleFormDraft:${location.pathname}#theGreatSampleForm`;
    const statusEl = document.getElementById('draftStatus');
    const clearBtn = section.querySelector('.btn-clear-draft') || document.querySelector('.btn-clear-draft');

    // --- helpers ---
    const debounce = (fn, ms = 400) => {
      let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
    };

    function showStatus(msg) {
      if (!statusEl) return;
      statusEl.textContent = msg;
      statusEl.hidden = false;
    }
    function hideStatus() {
      if (!statusEl) return;
      statusEl.hidden = true;
      statusEl.textContent = '';
    }

    // Serialize current form values (skip passwords)
    function readForm() {
      const data = {};
      const fields = form.querySelectorAll('input, select, textarea');
      fields.forEach(el => {
        if (!el.name) return;
        if (el.disabled) return;
        if (el.matches('[data-nosave]')) return;
        if (el.type === 'password') return; // privacy: never store passwords

        if (el.type === 'radio') {
          if (el.checked) data[el.name] = el.value;
          return;
        }
        if (el.type === 'checkbox') {
          // for checkboxes with same name, store array
          if (data[el.name] === undefined) data[el.name] = [];
          if (el.checked) data[el.name].push(el.value || true);
          return;
        }
        if (el.tagName === 'SELECT' && el.multiple) {
          data[el.name] = Array.from(el.selectedOptions).map(o => o.value);
          return;
        }
        // default text-like
        data[el.name] = el.value;
      });
      return { data, ts: Date.now() };
    }

    // Apply saved values back into the form
    function writeForm(saved) {
      if (!saved || !saved.data) return;
      const data = saved.data;

      Object.keys(data).forEach(name => {
        const value = data[name];
        const controls = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);
        if (!controls.length) return;

        controls.forEach(el => {
          if (el.type === 'password') return;

          if (el.type === 'radio') {
            el.checked = (value === el.value);
            return;
          }
          if (el.type === 'checkbox') {
            if (Array.isArray(value)) {
              el.checked = value.includes(el.value || true);
            } else {
              el.checked = !!value;
            }
            return;
          }
          if (el.tagName === 'SELECT' && el.multiple && Array.isArray(value)) {
            Array.from(el.options).forEach(opt => opt.selected = value.includes(opt.value));
            return;
          }
          el.value = typeof value === 'string' ? value : (value ?? '');
        });
      });

      showStatus('Restored your in-progress form.');
      if (clearBtn) clearBtn.hidden = false;

      // Trigger any dependent UI (e.g., your per-field validators)
      form.dispatchEvent(new Event('input', { bubbles: true }));
      form.dispatchEvent(new Event('change', { bubbles: true }));
    }

    let lastSaved = null;

    const saveDraft = debounce(() => {
      const payload = readForm();
      const str = JSON.stringify(payload);
      if (str === lastSaved) return; // nothing changed
      lastSaved = str;
      try {
        localStorage.setItem(DRAFT_KEY, str);
        if (clearBtn) clearBtn.hidden = false;
      } catch { }
    }, 400);


    // Hook up events to save
    form.addEventListener('input', saveDraft);
    form.addEventListener('change', saveDraft);

    form.addEventListener('reset', () => {
      // 1) Clear saved draft
      try { localStorage.removeItem(DRAFT_KEY); } catch { }

      // 2) Remove any processing state
      section.classList.remove('form-processing');

      // 3) Strip status classes + messages
      const stripStateClasses = (el) => {
        el.classList.forEach(c => {
          if (
            c.startsWith('is-') ||
            c === 'valid' || c === 'invalid' ||
            c === 'partial-valid' || c === 'one-valid' ||
            c === 'two-valid' || c === 'mismatch'
          ) el.classList.remove(c);
        });
        // clear any inline text used for status/live messages
        el.textContent = '';
      };

      section.querySelectorAll(
        '.username-status, .name-status, .email-status, .phone-status, .password-status'
      ).forEach(stripStateClasses);

      // 4) Clear per-input flags
      section.querySelectorAll('input, select, textarea').forEach(el => {
        el.removeAttribute('aria-invalid');
        el.classList.remove('partial-valid');
      });

      // 5) (Optional) Set initial “empty” state where you use it
      section.querySelector('.username-status')?.classList.add('is-empty');

      // 6) Let any field listeners recompute baseline UI
      setTimeout(() => {
        form.dispatchEvent(new Event('input', { bubbles: true }));
        form.dispatchEvent(new Event('change', { bubbles: true }));
      }, 0);
    });

    // If clearBtn might be absent, guard it:
    if (clearBtn) {
      clearBtn.addEventListener('click', () => form.reset());
    }

    // Restore on load
    try {
      const raw = localStorage.getItem(DRAFT_KEY);
      if (raw) writeForm(JSON.parse(raw));
    } catch { /* ignore parse/storage errors */ }

    // Restore again on pageshow (covers browser back/forward cache)
    window.addEventListener('pageshow', () => {
      try {
        const raw = localStorage.getItem(DRAFT_KEY);
        if (raw) writeForm(JSON.parse(raw));
      } catch { }
    });


    // On successful “fake submit”, wipe the draft so the success page doesn’t show old values later
    form.addEventListener('submit', () => {
      try { localStorage.removeItem(DRAFT_KEY); } catch { }
    });
  })();
</script>
