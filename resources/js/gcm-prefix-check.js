/*
 * Live "is this short name taken?" check for the create forms of facilities,
 * client companies and contractors (the 3-letter prefix their ID is built
 * from). The Store*Request unique rule stays the real gatekeeper — this only
 * shows the message while the user types instead of after "Submit".
 *
 *   const prefixCheck = window.gcmPrefixCheck(prefixInput, {
 *     url: '/api/v1/facilities/prefix-available',
 *     message: t.prefix_taken
 *   });
 *   ...in validate():  if (prefixCheck.isTaken()) fail(prefixInput, t.prefix_taken);
 *
 * Does nothing for a locked field (edit pages) or until 3 letters are typed.
 * A failed request is ignored — the server re-checks on save anyway.
 */
window.gcmPrefixCheck = function (input, { url, message }) {
  let taken = false;
  let timer = null;
  let seq = 0;

  const feedback = () => {
    const wrapper = input.closest('[class*="col-"]') || input.parentElement;
    return wrapper ? wrapper.querySelector('[data-feedback]') : null;
  };

  const reset = () => {
    taken = false;
    seq += 1; // an answer still in flight is for text that's gone
    clearTimeout(timer);
  };

  const check = () => {
    const value = input.value;
    if (input.disabled || input.readOnly || !/^[A-Z]{3}$/.test(value)) return;

    const mine = ++seq;
    window.axios
      .get(url, { params: { prefix: value } })
      .then((res) => {
        if (mine !== seq || input.value !== value || res.data.available !== false) return;
        taken = true;
        input.classList.add('is-invalid');
        const fb = feedback();
        if (fb) {
          fb.textContent = message;
          fb.classList.remove('d-none');
        }
      })
      .catch(() => {});
  };

  input.addEventListener('input', () => {
    reset();
    timer = setTimeout(check, 350);
  });
  input.addEventListener('blur', () => {
    clearTimeout(timer);
    if (!taken) check();
  });

  return { isTaken: () => taken };
};
