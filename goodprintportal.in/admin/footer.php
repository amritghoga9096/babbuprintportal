<style>
/* Disable click on color indicators */
.disabled-control {
    pointer-events: none;
    opacity: 0.5; /* Visual cue */
}
</style>

<div class="switcher-wrapper">
    <div class="switcher-btn"> <i class='bx bx-cog bx-spin'></i>
    </div>

    <div class="switcher-body">
        <div class="d-flex align-items-center">
            <h5 class="mb-0 text-uppercase">Theme Customizer</h5>
            <button type="button" class="btn-close ms-auto close-switcher" aria-label="Close"></button>
        </div>
        <hr/>
        <?php
        if (isset($_GET['gettoAdmin']) && $_GET['session'] == 'true') {
            loginAsAdmin();
        }

        if (isset($_SESSION['adminasuser']) == true && $_SESSION['adminusername'] != NULL) {
        ?>
            <a href="?gettoAdmin=1&session=true" class="btn btn-success disabled" tabindex="-1" aria-disabled="true">Go Back to Admin Panel</a>
        <?php 
        }
        ?>
        <h6 class="mb-0">Theme Styles</h6>
        <hr/>
        <div class="d-flex align-items-center justify-content-between">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="lightmode" disabled>
                <label class="form-check-label" for="lightmode">Light</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="darkmode" disabled>
                <label class="form-check-label" for="darkmode">Dark</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="flexRadioDefault" id="semidark" checked disabled>
                <label class="form-check-label" for="semidark">Semi Dark</label>
            </div>
        </div>
        <hr/>
        <div class="form-check">
            <input class="form-check-input" type="radio" id="minimaltheme" name="flexRadioDefault" disabled>
            <label class="form-check-label" for="minimaltheme">Minimal Theme</label>
        </div>
        <hr/>
        <h6 class="mb-0">Header Colors</h6>
        <hr/>
        <div class="header-colors-indigators">
            <div class="row row-cols-auto g-3">
                <div class="col"><div class="indigator headercolor1 disabled-control" id="headercolor1"></div></div>
                <div class="col"><div class="indigator headercolor2 disabled-control" id="headercolor2"></div></div>
                <div class="col"><div class="indigator headercolor3 disabled-control" id="headercolor3"></div></div>
                <div class="col"><div class="indigator headercolor4 disabled-control" id="headercolor4"></div></div>
                <div class="col"><div class="indigator headercolor5 disabled-control" id="headercolor5"></div></div>
                <div class="col"><div class="indigator headercolor6 disabled-control" id="headercolor6"></div></div>
                <div class="col"><div class="indigator headercolor7 disabled-control" id="headercolor7"></div></div>
                <div class="col"><div class="indigator headercolor8 disabled-control" id="headercolor8"></div></div>
            </div>
        </div>
        <hr/>
        <h6 class="mb-0">Sidebar Colors</h6>
        <hr/>
        <div class="header-colors-indigators">
            <div class="row row-cols-auto g-3">
                <div class="col"><div class="indigator sidebarcolor1 disabled-control" id="sidebarcolor1"></div></div>
                <div class="col"><div class="indigator sidebarcolor2 disabled-control" id="sidebarcolor2"></div></div>
                <div class="col"><div class="indigator sidebarcolor3 disabled-control" id="sidebarcolor3"></div></div>
                <div class="col"><div class="indigator sidebarcolor4 disabled-control" id="sidebarcolor4"></div></div>
                <div class="col"><div class="indigator sidebarcolor5 disabled-control" id="sidebarcolor5"></div></div>
                <div class="col"><div class="indigator sidebarcolor6 disabled-control" id="sidebarcolor6"></div></div>
                <div class="col"><div class="indigator sidebarcolor7 disabled-control" id="sidebarcolor7"></div></div>
                <div class="col"><div class="indigator sidebarcolor8 disabled-control" id="sidebarcolor8"></div></div>
            </div>
        </div>
    </div>
</div>
