(function () {

    var oLoginViewModel;
    AfterLogicApi.addPluginHook('view-model-defined', function (sViewModelName, oViewModel) {

        if (oViewModel && ('CLoginViewModel' === sViewModelName))
        {
            oLoginViewModel = oViewModel;
        }
    });

	AfterLogicApi.addPluginHook('ajax-default-request', function (sAction, oParameters) {
        if (('SystemLogin' === sAction)) {
            this.oParams = oParameters;
        }
	});

    AfterLogicApi.addPluginHook('ajax-default-response', function (sAction, oData) {
        if (('SystemLogin' === sAction && oData.Result != false && oData.ContinueAuth != true))
        {
                oData['StopExecuteResponse'] = true;
                AfterLogicApi.showPopup(VerifyTokenPopup, [this.oParams.Email, oLoginViewModel]);
        }
    });

}());