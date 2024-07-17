<?php

/**
 * @file classes/log/SubmissionEmailLogEventType.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @enum SubmissionEmailLogEventType
 *
 * @ingroup log
 *
 * @brief Enumeration for submission email log event types.
 */

namespace PKP\log;

use PKP\log\core\EmailLogEventType;

enum SubmissionEmailLogEventType: int implements EmailLogEventType
{
    // Author events						0x20000000
    case AUTHOR_NOTIFY_REVISED_VERSION = 0x20000001;
    case AUTHOR_SUBMISSION_ACK = 0x20000002;

    // Editor events						0x30000000
    case EDITOR_NOTIFY_AUTHOR = 0x30000001;
    case EDITOR_ASSIGN = 0x30000002;
    case EDITOR_NOTIFY_AUTHOR_UNSUITABLE = 0x30000003;
    case EDITOR_RECOMMEND_NOTIFY = 0x30000004;
    case NEEDS_EDITOR = 0x30000005;

    // Reviewer events						0x40000000
    case REVIEW_NOTIFY_REVIEWER = 0x40000001;
    case REVIEW_THANK_REVIEWER = 0x40000002;
    case REVIEW_CANCEL = 0x40000003;
    case REVIEW_REMIND = 0x40000004;
    case REVIEW_CONFIRM = 0x40000005;
    case REVIEW_DECLINE = 0x40000006;
    case REVIEW_CONFIRM_ACK = 0x40000008;

    // Copyeditor events						0x50000000
    case COPYEDIT_NOTIFY_COPYEDITOR = 0x50000001;
    case COPYEDIT_NOTIFY_AUTHOR = 0x50000002;
    case COPYEDIT_NOTIFY_FINAL = 0x50000003;
    case COPYEDIT_NOTIFY_COMPLETE = 0x50000004;
    case COPYEDIT_NOTIFY_AUTHOR_COMPLETE = 0x50000005;
    case COPYEDIT_NOTIFY_FINAL_COMPLETE = 0x50000006;
    case COPYEDIT_NOTIFY_ACKNOWLEDGE = 0x50000007;
    case COPYEDIT_NOTIFY_AUTHOR_ACKNOWLEDGE = 0x50000008;
    case COPYEDIT_NOTIFY_FINAL_ACKNOWLEDGE = 0x50000009;

    // Proofreader events						0x60000000
    case PROOFREAD_NOTIFY_AUTHOR = 0x60000001;
    case PROOFREAD_NOTIFY_AUTHOR_COMPLETE = 0x60000002;
    case PROOFREAD_THANK_AUTHOR = 0x60000003;
    case PROOFREAD_NOTIFY_PROOFREADER = 0x60000004;
    case PROOFREAD_NOTIFY_PROOFREADER_COMPLETE = 0x60000005;
    case PROOFREAD_THANK_PROOFREADER = 0x60000006;
    case PROOFREAD_NOTIFY_LAYOUTEDITOR = 0x60000007;
    case PROOFREAD_NOTIFY_LAYOUTEDITOR_COMPLETE = 0x60000008;
    case PROOFREAD_THANK_LAYOUTEDITOR = 0x60000009;

    // Layout events						0x70000000
    case LAYOUT_NOTIFY_EDITOR = 0x70000001;
    case LAYOUT_THANK_EDITOR = 0x70000002;
    case LAYOUT_NOTIFY_COMPLETE = 0x70000003;

    // Index events                         0x80000000
    case INDEX_NOTIFY_INDEXER = 0x80000001;
    case INDEX_NOTIFY_COMPLETE = 0x80000002;

    // Discussion
    case DISCUSSION_NOTIFY = 0x90000001;
}
