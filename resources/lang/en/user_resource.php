<?php

return [
    'delete_blocked' => [
        'title' => 'Cannot delete user',
        'body' => 'This user is linked to a doctor profile. Delete the doctor record instead — the login account will be removed automatically.',
        'bulk_body' => ':count of the selected users are linked to doctor profiles and cannot be deleted from here. Delete the matching doctor records instead.',
    ],
];
