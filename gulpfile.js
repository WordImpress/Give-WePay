/**
 *  Give Gulp File
 *
 *  @description: Used for POT file creation
 */

/* Modules (Can be installed with npm install command using package.json)
 ------------------------------------- */
var gulp = require('gulp'),
    sort = require('gulp-sort'),
    wpPot = require('gulp-wp-pot');


/* POT file task
 ------------------------------------- */
gulp.task('pot', function () {
    return gulp.src('**/*.php')
        .pipe(sort())
        .pipe(wpPot({
            package: 'Give - WePay Payment Gateway',
            domain: 'give-wepay', //textdomain
            destFile: 'give-wepay.pot',
            bugReport: 'https://github.com/WordImpress/Give-WePay/issues/new',
            lastTranslator: '',
            team: 'WordImpress <info@wordimpress.com>'
        }))
        .pipe(gulp.dest('languages'));
});


/* Default Gulp task
 ------------------------------------- */
gulp.task('default', function () {
    // Run all the tasks!
    gulp.start('pot');
});
