<?php

function afterburner_process_css($css, $theme) {

    global $CFG;

    // Set the background image for the logo
    if (!empty($theme->settings->logo)) {
        $logo = $theme->settings->logo;
    } else {
        $logo = null;
    }
    $css = afterburner_set_logo($css, $logo);

    // Set custom CSS
    if (!empty($theme->settings->customcss)) {
        $customcss = $theme->settings->customcss;
    } else {
        $customcss = null;
    }
    $css = afterburner_set_customcss($css, $customcss);

    $tag = '[[font:252251_0_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_0_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_0_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_0_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_0_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_0_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_0_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_0_0.woff';
    $css = str_replace($tag, $replacement, $css);

 

    $tag = '[[font:252251_1_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_1_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_1_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_1_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_1_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_1_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_1_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_1_0.woff';
    $css = str_replace($tag, $replacement, $css);



    $tag = '[[font:252251_2_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_2_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_2_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_2_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_2_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_2_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_2_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_2_0.woff';
    $css = str_replace($tag, $replacement, $css);



    $tag = '[[font:252251_3_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_3_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_3_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_3_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_3_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_3_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_3_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_3_0.woff';
    $css = str_replace($tag, $replacement, $css);


    $tag = '[[font:252251_4_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_4_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_4_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_4_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_4_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_4_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_4_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_4_0.woff';
    $css = str_replace($tag, $replacement, $css);



    $tag = '[[font:252251_5_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_5_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_5_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_5_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_5_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_5_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_5_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_5_0.woff';
    $css = str_replace($tag, $replacement, $css);



    $tag = '[[font:252251_6_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_6_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_6_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_6_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_6_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_6_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_6_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_6_0.woff';
    $css = str_replace($tag, $replacement, $css);



    $tag = '[[font:252251_7_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_7_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_7_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_7_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_7_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_7_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_7_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_7_0.woff';
    $css = str_replace($tag, $replacement, $css);



    $tag = '[[font:252251_8_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_8_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_8_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_8_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_8_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_8_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_8_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_8_0.woff';
    $css = str_replace($tag, $replacement, $css);



    $tag = '[[font:252251_9_0-eot]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_9_0.eot';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_9_0-ttf]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_9_0.ttf';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_9_0-eot-ie]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_9_0.eot?#iefix';
    $css = str_replace($tag, $replacement, $css);

    $tag = '[[font:252251_9_0-woff]]';
    $replacement = $CFG->wwwroot.'/theme/afterburner/pix/fonts/252251_9_0.woff';
    $css = str_replace($tag, $replacement, $css);

    return $css;
}

function afterburner_set_logo($css, $logo) {
    global $OUTPUT;
    $tag = '[[setting:logo]]';
    $replacement = $logo;
    if (is_null($replacement)) {
        $replacement = $OUTPUT->pix_url('images/logo','theme');
    }

    $css = str_replace($tag, $replacement, $css);

    return $css;
}

function afterburner_set_customcss($css, $customcss) {
    $tag = '[[setting:customcss]]';
    $replacement = $customcss;
    if (is_null($replacement)) {
        $replacement = '';
    }

    $css = str_replace($tag, $replacement, $css);

    return $css;
}