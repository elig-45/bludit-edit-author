<?php
/**
 * Plugin: Editable Author Select
 * Description: Make the author field editable with a select2 dropdown on existing users.
 * Author: elig-45
 * License: MIT
 */

class EditableAuthorSelect extends Plugin
{
    /**
     * Guard to prevent recursive edits when updating author.
     *
     * @var bool
     */
    private static $editing = false;

    /**
     * Injects author selection UI into the admin head.
     *
     * @return string
     */
    public function adminHead()
    {
        try {
            $authors = $this->loadAuthors();
            $jsonAuthors = json_encode($authors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        } catch (Throwable $e) {
            error_log('[EditableAuthorSelect] adminHead error: ' . $e->getMessage());
            return '';
        }

        if ($jsonAuthors === false) {
            $jsonAuthors = '[]';
        }

        // Localized strings (English default, French override).
        $labelText = 'Author';
        $placeholderText = 'Start typing an author...';
        $chooseText = 'Choose an author...';
        global $site;
        $langCode = (isset($site) && is_object($site) && method_exists($site, 'language')) ? (string) $site->language() : '';
        if (stripos($langCode, 'fr') === 0) {
            $labelText = 'Auteur';
            $placeholderText = 'Commencez à taper un auteur...';
            $chooseText = 'Choisissez un auteur...';
        }

        $script = <<<'HTML'
<script>
/* EditableAuthorSelect */
(function() {
  var authors = __AUTHORS__;
  var labelText = "__LABEL_TEXT__";
  var placeholderText = "__PLACEHOLDER_TEXT__";
  var chooseText = "__CHOOSE_TEXT__";

  function buildLabel(author) {
    var full = ((author.firstName || '') + ' ' + (author.lastName || '')).trim();
    var nickname = author.nickname || '';
    if (full) {
      return nickname ? (full + ' (' + nickname + ')') : full;
    }
    if (nickname) {
      return nickname + ' (' + author.username + ')';
    }
    return author.username;
  }

  function normalizeAuthors(list) {
    if (!Array.isArray(list)) {
      return [];
    }
    return list.map(function(a) {
      return {
        id: a.username,
        text: buildLabel(a),
        firstName: a.firstName || '',
        lastName: a.lastName || '',
        nickname: a.nickname || ''
      };
    });
  }

  function initOnce() {
    var group = document.querySelector("label[for='js']") ? document.querySelector("label[for='js']").closest('.form-group') : null;
    var input = document.querySelector("input#js, input[name='username']");
    if (!group || !input) {
      return false;
    }

    var current = input.value || '';
    var normalized = normalizeAuthors(authors);

    // Rebuild the block to replace the disabled input.
    group.innerHTML = '';

    var label = document.createElement('label');
    label.className = 'mt-4 mb-2 pb-2 border-bottom text-uppercase w-100';
    label.setAttribute('for', 'jsusername-select');
    label.textContent = labelText;
    group.appendChild(label);

    var hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'username';
    hidden.id = 'jsusername-hidden';
    hidden.value = current;
    group.appendChild(hidden);

    var select = document.createElement('select');
    select.id = 'jsusername-select';
    select.className = 'form-control';
    group.appendChild(select);

    var $jq = window.jQuery || window.$;
    if ($jq && $jq.fn && $jq.fn.select2) {
      var $select = $jq(select);
      $select.select2({
        data: normalized,
        theme: 'bootstrap4',
        tags: false,
        allowClear: true,
        placeholder: placeholderText,
        minimumInputLength: 0,
        width: '100%',
        templateResult: function (d) { return d.text; },
        templateSelection: function (d) { return d.text || d.id; }
      });

      if (current) {
        var exists = normalized.some(function (d) { return d.id === current; });
        if (exists) {
          $select.val(current).trigger('change');
        }
      }

      $select.on('change', function () {
        var val = $select.val();
        hidden.value = Array.isArray(val) ? val[0] : (val || '');
      });

      $select.on('select2:open', function () {
        var search = document.querySelector('.select2-container--open .select2-search__field');
        if (search) {
          search.focus();
        }
      });
    } else {
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = chooseText;
      placeholder.disabled = true;
      placeholder.selected = !(current || '');
      select.appendChild(placeholder);

      normalized.forEach(function (author) {
        var opt = document.createElement('option');
        opt.value = author.id;
        opt.textContent = author.text;
        select.appendChild(opt);
      });

      select.addEventListener('change', function () {
        hidden.value = select.value;
      });
    }

    return true;
  }

  // Try immediately, then retry for a short window to catch late rendering.
  if (!initOnce()) {
    var attempts = 0;
    var interval = setInterval(function () {
      attempts++;
      if (initOnce() || attempts > 40) {
        clearInterval(interval);
      }
    }, 150);
  }
})();
</script>
HTML;

        $replacements = array(
            '__AUTHORS__' => $jsonAuthors,
            '__LABEL_TEXT__' => htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8'),
            '__PLACEHOLDER_TEXT__' => htmlspecialchars($placeholderText, ENT_QUOTES, 'UTF-8'),
            '__CHOOSE_TEXT__' => htmlspecialchars($chooseText, ENT_QUOTES, 'UTF-8'),
        );

        return strtr($script, $replacements);
    }

    /**
     * After creating a page, allow updating the author from POST data.
     *
     * @param string $key
     */
    public function afterPageCreate($key)
    {
        $this->setAuthorFromPost($key);
    }

    /**
     * After modifying a page, allow updating the author from POST data.
     *
     * @param string $key
     */
    public function afterPageModify($key)
    {
        $this->setAuthorFromPost($key);
    }

    /**
     * Update the page author based on POSTed username with safety checks.
     *
     * @param string $key
     */
    private function setAuthorFromPost($key)
    {
        global $pages, $login;

        if (self::$editing) {
            return;
        }

        if (!$login || $login->role() !== 'admin') {
            return;
        }

        if (!isset($_POST['username'])) {
            return;
        }

        $newUsername = trim($_POST['username']);
        if ($newUsername === '') {
            return;
        }

        if (!$this->userExists($newUsername)) {
            return;
        }

        self::$editing = true;

        try {
            $pages->edit(array(
                'key' => $key,
                'username' => $newUsername
            ));
        } finally {
            self::$editing = false;
        }
    }

    /**
     * Check if the provided username exists in the users database.
     *
     * @param string $username
     * @return bool
     */
    private function userExists($username)
    {
        if ($username === '') {
            return false;
        }

        $dbUsers = $this->getUsersTable();
        return isset($dbUsers[$username]);
    }

    /**
     * Return users data from the runtime Users object or the DB file.
     *
     * @return array
     */
    private function getUsersTable()
    {
        global $users;

        $sourceUsers = null;
        if (isset($users) && is_object($users)) {
            $sourceUsers = $users;
        } elseif (class_exists('Users')) {
            // Fallback: instantiate a local Users object (does not emit output).
            $sourceUsers = new Users();
        }

        if ($sourceUsers) {
            if (method_exists($sourceUsers, 'getDB')) {
                $db = $sourceUsers->getDB();
                if (is_array($db)) {
                    return $db;
                }
            }

            if (method_exists($sourceUsers, 'getDb')) {
                $db = $sourceUsers->getDb();
                if (is_array($db)) {
                    return $db;
                }
            }

            if (method_exists($sourceUsers, 'keys') && method_exists($sourceUsers, 'getUserDB')) {
                $collected = array();
                foreach ($sourceUsers->keys() as $name) {
                    $row = $sourceUsers->getUserDB($name);
                    if ($row !== false && is_array($row)) {
                        $collected[$name] = $row;
                    }
                }
                if (!empty($collected)) {
                    return $collected;
                }
            }
        }

        return $this->readUsersDbFile();
    }

    /**
     * Safely read the DB_USERS file to extract the raw users array.
     *
     * @return array
     */
    private function readUsersDbFile()
    {
        $result = array();

        if (defined('DB_USERS') && file_exists(DB_USERS)) {
            $raw = @file_get_contents(DB_USERS);
            if ($raw !== false) {
                // Strip any PHP header block if present.
                if (strpos($raw, '<?php') === 0) {
                    $pos = strpos($raw, '?>');
                    if ($pos !== false) {
                        $raw = substr($raw, $pos + 2);
                    }
                }
                $raw = trim($raw);
                $dbUsers = json_decode($raw, true);
                if (is_array($dbUsers)) {
                    $result = $dbUsers;
                }
            }
        }

        return $result;
    }

    /**
     * Build authors list from runtime Users or DB_USERS fallback.
     *
     * @return array
     */
    private function loadAuthors()
    {
        $authors = array();
        $dbUsers = $this->getUsersTable();

        foreach ($dbUsers as $username => $row) {
            $firstName = is_array($row) && isset($row['firstName']) ? $row['firstName'] : '';
            $lastName = is_array($row) && isset($row['lastName']) ? $row['lastName'] : '';
            $nickname = is_array($row) && isset($row['nickname']) ? $row['nickname'] : '';
            $authors[] = $this->buildAuthorEntry($username, $firstName, $lastName, $nickname);
        }

        return $authors;
    }

    /**
     * Helper to format author entry.
     *
     * @param string $username
     * @param string $firstName
     * @param string $lastName
     * @param string $nickname
     * @return array
     */
    private function buildAuthorEntry($username, $firstName, $lastName, $nickname)
    {
        return array(
            'username' => $username,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'nickname' => $nickname
        );
    }
}
