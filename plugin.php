<?php
/**
 * Plugin: Editable Author Select
 * Description: Make the author field editable with autocomplete on existing users.
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
     * Injects author autocomplete script in the admin head for new/edit content views.
     *
     * @return string
     */
    public function adminHead()
    {
        $view = isset($_GET['view']) ? $_GET['view'] : '';
        if ($view !== 'new-content' && $view !== 'edit-content') {
            return '';
        }

        global $users;

        $authors = array();
        foreach ($users->keys() as $username) {
            $user = $users->get($username);
            if ($user === false) {
                continue;
            }

            $label = $username;
            $nickname = $user->nickname();
            if (!empty($nickname)) {
                $label .= ' - ' . $nickname;
            }

            $authors[] = array(
                'username' => $username,
                'label' => $label
            );
        }

        $jsonAuthors = json_encode($authors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        return <<<HTML
<script>
  window.BLUDIT_AUTHORS = {$jsonAuthors};
  document.addEventListener('DOMContentLoaded', function () {
    var input = document.querySelector("input[name='username']");
    if (!input) {
      return;
    }

    input.removeAttribute('disabled');
    input.setAttribute('placeholder', 'Type or select an existing author...');

    var dataListId = 'bludit-author-list';
    var existingList = document.getElementById(dataListId);
    if (existingList) {
      existingList.parentNode.removeChild(existingList);
    }

    var dataList = document.createElement('datalist');
    dataList.id = dataListId;

    (window.BLUDIT_AUTHORS || []).forEach(function (author) {
      var option = document.createElement('option');
      option.value = author.username;
      option.label = author.label;
      dataList.appendChild(option);
    });

    document.body.appendChild(dataList);
    input.setAttribute('list', dataListId);
  });
</script>
HTML;
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
        global $pages, $users, $login;

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

        if ($users->get($newUsername) === false) {
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
}
