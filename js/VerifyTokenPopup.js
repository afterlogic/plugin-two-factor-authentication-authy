/**
 * @constructor
 */
function VerifyTokenPopup()
{
	this.status = ko.observable('');
    this.code = ko.observable('');
    this.email = ko.observable('');

	this.fCallback = null;
	this.verifyButtonText = ko.observable(AfterLogicApi.i18n('2F_AUTHY_PLUGIN/VERIFY'));

	this.iAutoReloadTimer = -1;
	this.opened = ko.observable(false);
    this.oLoginViewModel = null;

    ko.bindingHandlers.inputFilter = {
        init: function (element, valueAccessor, allBindingsAccessor, viewModel)
        {
            var filter = ko.utils.unwrapObservable(valueAccessor());

            if (filter == 'numbers') {
                $(element).keydown(function (event)
                {
                    if (event.keyCode == 8 // backspace
                        || event.keyCode == 9 // tab
                        || event.keyCode == 13 // enter
                        || event.keyCode == 27 // escape
                        || (event.keyCode >= 35 && event.keyCode <= 39) // end, home, left arrow, up arrow, right arrow
                        || event.keyCode == 46 // del
                        || (event.keyCode == 65 && event.ctrlKey === true) // ctrl + a
                        || (event.keyCode >= 112 && event.keyCode <= 123)) // f1, f2, f3, f4, f5, f6, f7, f8, f9, f10, f11, f12
                    {
                        // OK, system key was pressed.
                        return true;
                    } else {
                        if (event.shiftKey // shift
                            || (event.keyCode < 48 || event.keyCode > 57) // digits
                            && (event.keyCode < 96 || event.keyCode > 105)) // numpad digits
                        {
                            // False, letter was pressed.
                            event.preventDefault();
                        } else {
                            // Ok, digit was pressed.
                            return true;
                        }
                    }
                }
                );
            }

        },
        update: function (element, valueAccessor, allBindingsAccessor, viewModel) {
        }
    };
}

/**
 * @return {string}
 */
VerifyTokenPopup.prototype.popupTemplate = function ()
{
	return 'Plugin_VerifyTokenPopup';
};

VerifyTokenPopup.prototype.setAutoReloadTimer = function ()
{
	var self = this;
	clearTimeout(this.iAutoReloadTimer);
	
	this.iAutoReloadTimer = setTimeout(function () {
		self.Status();
	}, 10 * 1000);
};

/**
 *
 * @param {string} sEmail
 * @param {object} oLoginViewModel
 */
VerifyTokenPopup.prototype.onShow = function (sEmail, oLoginViewModel)
{
	this.email = sEmail;
    this.oLoginViewModel = oLoginViewModel;
	this.opened(true);
};

VerifyTokenPopup.prototype.Status = function ()
{
	this.setAutoReloadTimer();
};

VerifyTokenPopup.prototype.StatusResponse = function (oResponse)
{
		this.status(oResponse.Result);

        this.verifyButtonText(AfterLogicApi.i18n('2F_AUTHY_PLUGIN/VERIFY'));

        if(this.status() == true)
        {
            window.location.reload();
        } else if (this.status() == false) {
            if (oResponse.ErrorMessage) AfterLogicApi.showError(oResponse.ErrorMessage);
            if (oResponse.ErrorMessage['message']) AfterLogicApi.showError(oResponse.ErrorMessage['message']);
        } else {
            AfterLogicApi.showError(AfterLogicApi.i18n('2F_AUTHY_PLUGIN/NOTICE'));
        }
};

VerifyTokenPopup.prototype.onVerifyClick = function ()
{
    App.Ajax.AddActionsWithoutAuthForSend('VerifyToken');

    App.Ajax.send({
            'Action': 'VerifyToken',
            'Code': this.code(),
            'Email': this.email,
            'SignMe': this.oLoginViewModel.signMe()
        },
        this.StatusResponse,
        this
    );

    this.verifyButtonText(AfterLogicApi.i18n('2F_AUTHY_PLUGIN/VERIFY_IN_PROGRESS'));

    this.setAutoReloadTimer();
};

VerifyTokenPopup.prototype.onCancelClick = function ()
{
	this.opened(false);
	this.closeCommand();

    if (this.oLoginViewModel)
    {
        this.oLoginViewModel.loading(false);
        this.code('');
    }
};

VerifyTokenPopup.prototype.onEscHandler = function ()
{
	this.onCancelClick();
};


