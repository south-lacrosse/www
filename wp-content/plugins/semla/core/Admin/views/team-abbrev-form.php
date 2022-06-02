<?php
if (self::$action === 'edit') {
    $title = 'Update';
} else {
    $title = 'Add New';
}
extract(self::$fields);
?>
<div class="wrap">
    <h1><?= $title ?> Abbreviation</h1>
<?php
if (self::$errors) {
    echo '<div class="notice notice-error is-dismissible"><p>' . implode('<br>', self::$errors) . '</p></div>';
}
?>    
    <form method="post">
        <table class="form-table">
            <tbody>
                <tr class="row-team">
                    <th scope="row">
                        <label for="team">Team<?= self::$action === 'new' ? ' <span class="description">(required)</span>' : '' ?></label>
                    </th>
                    <td>
<?php               if (self::$action === 'new') { ?>
                        <input type="text" name="team" id="team" class="regular-text" placeholder="Team" value="<?= esc_attr($team) ?>" required="required" />
<?php               } else {
                        echo esc_attr($team);
                    } ?>
                    </td>
                </tr>
                <tr class="row-abbrev">
                    <th scope="row">
                        <label for="abbrev">Abbreviation <span class="description">(required)</span></label>
                    </th>
                    <td>
                        <input type="text" name="abbrev" id="abbrev" class="regular-text" placeholder="Abbreviation" value="<?= esc_attr($abbrev) ?>" required="required" />
                    </td>
                </tr>
             </tbody>
        </table>
        <?php wp_nonce_field( 'semla_team_abbrev' ); ?>
        <?php submit_button( $title . ' Abbreviation', 'primary', 'submit' ); ?>
    </form>
</div>