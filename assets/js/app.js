/**
 * app.js — Resume Analyzer: drop-zone, skill picker, manage-skills modal, form submit
 */
(function () {
    'use strict';

    /* ── DOM refs ───────────────────────────────────────────────── */
    const form        = document.getElementById('resumeForm');
    const fileInput   = document.getElementById('resumeFile');
    const submitBtn   = document.getElementById('submitBtn');
    const btnText     = document.getElementById('btnText');
    const btnSpinner  = document.getElementById('btnSpinner');
    const alertBox    = document.getElementById('alertBox');
    const alertMsg    = document.getElementById('alertMsg');
    const dropZone    = document.getElementById('dropZone');
    const fileLabel   = document.getElementById('fileLabel');
    const fileNameEl  = document.getElementById('fileName');

    const skillPool               = document.getElementById('skillPool');
    const selectedSkillsContainer = document.getElementById('selectedSkillsContainer');
    const skillInputs             = document.getElementById('skillInputs');
    const noSkillsMsg             = document.getElementById('noSkillsMsg');
    const skillPickerError        = document.getElementById('skillPickerError');

    const MAX_BYTES   = 2 * 1024 * 1024;
    const ALLOWED_EXT = ['pdf', 'docx', 'txt'];

    /* ── Generic helpers ────────────────────────────────────────── */

    function showAlert(msg) {
        alertMsg.textContent = msg;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function hideAlert() {
        alertBox.classList.add('d-none');
        alertMsg.textContent = '';
    }

    function setLoading(on) {
        submitBtn.disabled = on;
        btnText.classList.toggle('d-none', on);
        btnSpinner.classList.toggle('d-none', !on);
    }

    function ext(filename) {
        return filename.split('.').pop().toLowerCase();
    }

    function validateFile(file) {
        if (!file) return 'Please select a resume file.';
        if (!ALLOWED_EXT.includes(ext(file.name)))
            return 'Unsupported file type. Please upload a PDF, DOCX, or TXT file.';
        if (file.size > MAX_BYTES)
            return 'File exceeds the 2 MB limit. Please upload a smaller file.';
        return null;
    }

    /* ── Drop-zone ──────────────────────────────────────────────── */

    function setFile(file) {
        if (!file) return;
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        fileNameEl.textContent = file.name;
        fileLabel.classList.remove('d-none');
        dropZone.classList.add('ra-drop-zone--selected');
        hideAlert();
    }

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('ra-drop-zone--active');
    });

    dropZone.addEventListener('dragleave', function (e) {
        if (!dropZone.contains(e.relatedTarget))
            dropZone.classList.remove('ra-drop-zone--active');
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('ra-drop-zone--active');
        const files = e.dataTransfer && e.dataTransfer.files;
        if (files && files.length > 0) setFile(files[0]);
    });

    fileInput.addEventListener('change', function () {
        const file = fileInput.files[0];
        if (file) {
            fileNameEl.textContent = file.name;
            fileLabel.classList.remove('d-none');
            dropZone.classList.add('ra-drop-zone--selected');
        } else {
            fileLabel.classList.add('d-none');
            dropZone.classList.remove('ra-drop-zone--selected');
        }
        hideAlert();
    });

    /* ── Skill picker ───────────────────────────────────────────── */

    function addSkill(id, name, poolBadge) {
        poolBadge.classList.add('ra-selected');
        poolBadge.removeAttribute('tabindex');

        const tag = document.createElement('span');
        tag.className  = 'ra-selected-tag';
        tag.dataset.id = id;
        tag.innerHTML  = '<span class="ra-tag-name">' + escHtml(name) + '</span>'
                       + ' <i class="bi bi-x ra-tag-remove" aria-label="Remove ' + escHtml(name) + '"></i>';
        tag.querySelector('.ra-tag-remove').addEventListener('click', function () {
            removeSkill(id, tag, poolBadge);
        });
        selectedSkillsContainer.appendChild(tag);

        const hidden       = document.createElement('input');
        hidden.type        = 'hidden';
        hidden.name        = 'skill_ids[]';
        hidden.value       = id;
        hidden.dataset.id  = id;
        skillInputs.appendChild(hidden);

        if (noSkillsMsg)      noSkillsMsg.style.display      = 'none';
        if (skillPickerError) skillPickerError.classList.add('d-none');
    }

    function removeSkill(id, tag, poolBadge) {
        tag.remove();
        const hidden = skillInputs ? skillInputs.querySelector('input[data-id="' + id + '"]') : null;
        if (hidden) hidden.remove();

        if (poolBadge) {
            poolBadge.classList.remove('ra-selected');
            poolBadge.setAttribute('tabindex', '0');
        }

        const remaining = selectedSkillsContainer
            ? selectedSkillsContainer.querySelectorAll('.ra-selected-tag') : [];
        if (remaining.length === 0 && noSkillsMsg) noSkillsMsg.style.display = '';
    }

    if (skillPool) {
        skillPool.addEventListener('click', function (e) {
            const badge = e.target.closest('.ra-skill-option');
            if (!badge || badge.classList.contains('ra-selected')) return;
            addSkill(badge.dataset.id, badge.dataset.name, badge);
        });

        skillPool.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const badge = e.target.closest('.ra-skill-option');
            if (badge && !badge.classList.contains('ra-selected')) {
                e.preventDefault();
                addSkill(badge.dataset.id, badge.dataset.name, badge);
            }
        });
    }

    /* ── Form submit ────────────────────────────────────────────── */

    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            hideAlert();

            const fileErr = validateFile(fileInput ? fileInput.files[0] : null);
            if (fileErr) { showAlert(fileErr); return; }

            const picked = skillInputs
                ? skillInputs.querySelectorAll('input[name="skill_ids[]"]') : [];
            if (picked.length === 0) {
                if (skillPickerError) skillPickerError.classList.remove('d-none');
                showAlert('Please select at least one skill to analyse.');
                return;
            }

            setLoading(true);

            fetch('analyzer/analyze.php', { method: 'POST', body: new FormData(form) })
                .then(function (r) {
                    if (!r.ok) throw new Error('Server error ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    if (data.success && data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        setLoading(false);
                        showAlert(data.error || 'An unexpected error occurred. Please try again.');
                    }
                })
                .catch(function (err) {
                    setLoading(false);
                    showAlert('Network error: ' + err.message);
                });
        });
    }

    /* ── Manage Skills modal ────────────────────────────────────── */

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function skillsApi(payload, cb) {
        fetch('analyzer/skills_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(payload)
        })
        .then(function (r) { return r.json(); })
        .then(cb)
        .catch(function (err) { cb({ success: false, error: err.message }); });
    }

    function poolBadgeById(id) {
        return skillPool ? skillPool.querySelector('.ra-skill-option[data-id="' + id + '"]') : null;
    }

    function buildModalRow(id, name) {
        const row = document.createElement('div');
        row.className     = 'ra-manage-skill-row';
        row.dataset.id    = id;

        const nameSpan    = document.createElement('span');
        nameSpan.className = 'ra-manage-skill-name';
        nameSpan.textContent = name;

        const actions     = document.createElement('div');
        actions.className = 'd-flex gap-2';

        const renameBtn   = document.createElement('button');
        renameBtn.type    = 'button';
        renameBtn.className = 'btn btn-sm btn-outline-secondary ra-rename-btn';
        renameBtn.innerHTML = '<i class="bi bi-pencil"></i>';
        renameBtn.title   = 'Rename';

        const deleteBtn   = document.createElement('button');
        deleteBtn.type    = 'button';
        deleteBtn.className = 'btn btn-sm btn-outline-danger ra-delete-btn';
        deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
        deleteBtn.title   = 'Delete';

        actions.appendChild(renameBtn);
        actions.appendChild(deleteBtn);
        row.appendChild(nameSpan);
        row.appendChild(actions);

        /* ── Rename ── */
        renameBtn.addEventListener('click', function () {
            if (row.querySelector('.ra-rename-input')) return; // already editing
            const input   = document.createElement('input');
            input.type    = 'text';
            input.className = 'form-control form-control-sm ra-rename-input';
            input.value   = nameSpan.textContent;
            input.maxLength = 60;
            nameSpan.replaceWith(input);
            renameBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
            renameBtn.title = 'Save';
            input.focus();
            input.select();

            function saveRename() {
                const newName = input.value.trim();
                if (!newName || newName === nameSpan.textContent) {
                    input.replaceWith(nameSpan);
                    renameBtn.innerHTML = '<i class="bi bi-pencil"></i>';
                    renameBtn.title = 'Rename';
                    return;
                }
                renameBtn.disabled = true;
                skillsApi({ action: 'rename', id: id, name: newName }, function (res) {
                    renameBtn.disabled = false;
                    if (res.success) {
                        nameSpan.textContent = newName;
                        input.replaceWith(nameSpan);
                        renameBtn.innerHTML = '<i class="bi bi-pencil"></i>';
                        renameBtn.title = 'Rename';
                        /* update pool badge */
                        const badge = poolBadgeById(id);
                        if (badge) {
                            badge.dataset.name = newName;
                            badge.innerHTML = '<i class="bi bi-plus me-1"></i>' + escHtml(newName);
                        }
                        /* update selected tag if present */
                        const selTag = selectedSkillsContainer
                            ? selectedSkillsContainer.querySelector('.ra-selected-tag[data-id="' + id + '"]') : null;
                        if (selTag) {
                            const n = selTag.querySelector('.ra-tag-name');
                            if (n) n.textContent = newName;
                        }
                    } else {
                        alert(res.error || 'Could not rename skill.');
                    }
                });
            }

            renameBtn.addEventListener('click', saveRename, { once: true });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); saveRename(); }
                if (e.key === 'Escape') {
                    input.replaceWith(nameSpan);
                    renameBtn.innerHTML = '<i class="bi bi-pencil"></i>';
                    renameBtn.title = 'Rename';
                }
            });
        });

        /* ── Delete ── */
        deleteBtn.addEventListener('click', function () {
            if (!confirm('Delete skill "' + nameSpan.textContent + '"? This cannot be undone.')) return;
            deleteBtn.disabled = true;
            skillsApi({ action: 'delete', id: id }, function (res) {
                if (res.success) {
                    row.remove();
                    /* remove from pool */
                    const badge = poolBadgeById(id);
                    if (badge) {
                        /* if the skill is currently selected, also remove the tag + hidden input */
                        if (badge.classList.contains('ra-selected')) {
                            const selTag = selectedSkillsContainer
                                ? selectedSkillsContainer.querySelector('.ra-selected-tag[data-id="' + id + '"]') : null;
                            if (selTag) removeSkill(id, selTag, null);
                        }
                        badge.remove();
                    }
                } else {
                    deleteBtn.disabled = false;
                    alert(res.error || 'Could not delete skill.');
                }
            });
        });

        return row;
    }

    function initManageModal() {
        const modal      = document.getElementById('manageSkillsModal');
        if (!modal) return;

        const addInput   = document.getElementById('newSkillInput');
        const addBtn     = document.getElementById('addSkillBtn');
        const addError   = document.getElementById('addSkillError');
        const skillsList = document.getElementById('manageSkillsList');

        if (!addInput || !addBtn || !skillsList) return;

        /* Populate list from existing pool badges */
        modal.addEventListener('show.bs.modal', function () {
            skillsList.innerHTML = '';
            if (!skillPool) return;
            skillPool.querySelectorAll('.ra-skill-option').forEach(function (badge) {
                skillsList.appendChild(buildModalRow(badge.dataset.id, badge.dataset.name));
            });
        });

        /* Add skill */
        addBtn.addEventListener('click', function () {
            const name = addInput.value.trim();
            if (!name) {
                addError.textContent = 'Please enter a skill name.';
                addError.classList.remove('d-none');
                return;
            }
            if (name.length > 60) {
                addError.textContent = 'Skill name must be 60 characters or fewer.';
                addError.classList.remove('d-none');
                return;
            }
            addError.classList.add('d-none');
            addBtn.disabled = true;

            skillsApi({ action: 'add', name: name }, function (res) {
                addBtn.disabled = false;
                if (res.success && res.skill) {
                    addInput.value = '';
                    const sid  = String(res.skill.id);
                    const sname = res.skill.skill_name;

                    /* Add badge to pool */
                    if (skillPool) {
                        const badge = document.createElement('span');
                        badge.className  = 'badge ra-skill-option';
                        badge.dataset.id = sid;
                        badge.dataset.name = sname;
                        badge.setAttribute('role', 'button');
                        badge.setAttribute('tabindex', '0');
                        badge.innerHTML  = '<i class="bi bi-plus me-1"></i>' + escHtml(sname);
                        skillPool.appendChild(badge);
                    }

                    /* Add row to modal list */
                    skillsList.appendChild(buildModalRow(sid, sname));
                } else {
                    addError.textContent = res.error || 'Could not add skill.';
                    addError.classList.remove('d-none');
                }
            });
        });

        addInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addBtn.click(); }
        });
    }

    initManageModal();

})();
