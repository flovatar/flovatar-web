<div class="newsletter newsletter-middle">
    <div class="container">
        <div class="heading">
            <h2>Join our community where all the fun really happens</h2>
        </div>
        <div class="form-content">
            <div class="form-content-holder">
                <div class="join-discord">
                    <div class="ico-wrap">
                        <a href="{{ config('flovatar.discordUrl') }}" target="_blank"><i class="icon-discord"></i></a>
                    </div>
                    <span class="txt">
                                    Join our <a href="{{ config('flovatar.discordUrl') }}" target="_blank"> Discord</a>
                                </span>
                </div>
                <div class="form-col">
                    <div class="form-wrap">
                        <form action="{{ route('subscribe') }}" method="POST">
                            <div x-show="showSubscription" class="form-row">
                                <div class="input-hold">
                                    <input x-model="emailSubscription" name="email" type="email" placeholder="Email address">
                                </div>
                                <button @click.prevent.stop="submitSubscription" type="submit" value="Get Notified" class="btn btn-lilac"><span class="inner">Sign up</span></button>
                            </div>
                            <div x-show="!showSubscription" class="success-message">Thank you</div>
                        </form>
                    </div>
                    <span class="join-newsletter">Join our Newsletter</span>
                </div>
            </div>
        </div>
    </div>
</div>
