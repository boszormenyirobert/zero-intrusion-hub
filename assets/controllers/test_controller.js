import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        renewUrl: String,
        checkUrl: String,
        domainProcessId: String,
        userLoginCsrf: String
    }

    static targets = ['qrCode', 'response']
connect() {
  console.log('Controller connected');
}
    async renew() {
        const response = await fetch(this.renewUrlValue, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.userLoginCsrfValue
            },
            body: JSON.stringify({
                domainProcessId: this.domainProcessIdValue
            })
        });

        const data = await response.json();
        console.log( data.authentication.domainProcessId);
        this.qrCodeTarget.src = 'data:image/png;base64,' + data.authentication.qrCode;
        this.domainProcessIdValue  =  data.authentication.domainProcessId;
    }

    async check() {
        const url = this.checkUrlValue + '?processId=' + encodeURIComponent(this.domainProcessIdValue);
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.userLoginCsrfValue
            }
        });

        const data = await response.json();
            if (data.jwt_token) {
                localStorage.setItem('jwt_token', data.jwt_token);
            }
        this.responseTarget.textContent = data.message;
        console.log(data);
        if(data.message === 'Authentication success.'){
            setTimeout(() => {
                window.location.href = '/';
            }, 1000);            
        }
    }

    deleteJwt(event) {
        console.log("delete");
        event.preventDefault();
        localStorage.removeItem('jwt_token');        
        window.location.href = '/user-logout';
        console.log("redirection");
        sessionStorage.removeItem('oneTouchUser');
        sessionStorage.removeItem('currentUser');
    }    
}
