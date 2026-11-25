#allowAccountLinking
// Passkey-signed (sponsor pays): link this passkey account to the user's wallet as a HybridCustody CHILD account.
// Everything the link needs lives in this account, no third-party factory/filter address:
//  - CapabilityFactory.Manager with the standard NFT + FT factories
//  - CapabilityFilter.AllowAllFilter: the parent wallet may use every NFT collection and FT vault of this account
//  - HybridCustody.OwnedAccount, published to `parent`; the wallet then claims it (hc_redeem_account.cdc).
// Keys are never touched: passkey sign-in keeps working and the wallet cannot remove the passkey.
// (HybridCustody "ownership" = giveOwnership() calls seal(), which revokes EVERY key incl. the passkeys -> not used.)
import NonFungibleToken from 0xNonFungibleToken
import FungibleToken from 0xFungibleToken
import MetadataViews from 0xMetadataViews
import ViewResolver from 0xViewResolver
import HybridCustody, CapabilityFactory, CapabilityFilter from 0xHybridCustody
import NFTCollectionPublicFactory, NFTProviderAndCollectionFactory, NFTProviderFactory, NFTCollectionFactory from 0xHybridCustody
import FTProviderFactory, FTBalanceFactory, FTReceiverBalanceFactory, FTReceiverFactory, FTAllFactory, FTVaultFactory from 0xHybridCustody

transaction(parent: Address, name: String, description: String, thumbnail: String) {
    prepare(acct: auth(Storage, Capabilities) &Account) {
        assert(parent != acct.address, message: "An account can't be its own parent")
        // 1. capability factories (what a parent can obtain, properly typed)
        if acct.storage.borrow<&AnyResource>(from: CapabilityFactory.StoragePath) == nil {
            acct.storage.save(<-CapabilityFactory.createFactoryManager(), to: CapabilityFactory.StoragePath)
        }
        if !acct.capabilities.get<&CapabilityFactory.Manager>(CapabilityFactory.PublicPath).check() {
            acct.capabilities.unpublish(CapabilityFactory.PublicPath)
            acct.capabilities.publish(acct.capabilities.storage.issue<&CapabilityFactory.Manager>(CapabilityFactory.StoragePath), at: CapabilityFactory.PublicPath)
        }
        let factories = acct.storage.borrow<auth(CapabilityFactory.Add) &CapabilityFactory.Manager>(from: CapabilityFactory.StoragePath)
            ?? panic("capability factory manager not found")
        factories.updateFactory(Type<&{NonFungibleToken.CollectionPublic}>(), NFTCollectionPublicFactory.Factory())
        factories.updateFactory(Type<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Provider, NonFungibleToken.CollectionPublic}>(), NFTProviderAndCollectionFactory.Factory())
        factories.updateFactory(Type<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Provider}>(), NFTProviderFactory.Factory())
        factories.updateFactory(Type<auth(NonFungibleToken.Withdraw) &{NonFungibleToken.Collection}>(), NFTCollectionFactory.WithdrawFactory())
        factories.updateFactory(Type<&{NonFungibleToken.Collection}>(), NFTCollectionFactory.Factory())
        factories.updateFactory(Type<auth(FungibleToken.Withdraw) &{FungibleToken.Provider}>(), FTProviderFactory.Factory())
        factories.updateFactory(Type<&{FungibleToken.Balance}>(), FTBalanceFactory.Factory())
        factories.updateFactory(Type<&{FungibleToken.Receiver}>(), FTReceiverFactory.Factory())
        factories.updateFactory(Type<&{FungibleToken.Receiver, FungibleToken.Balance}>(), FTReceiverBalanceFactory.Factory())
        factories.updateFactory(Type<auth(FungibleToken.Withdraw) &{FungibleToken.Provider, FungibleToken.Receiver, FungibleToken.Balance}>(), FTAllFactory.Factory())
        factories.updateFactory(Type<auth(FungibleToken.Withdraw) &{FungibleToken.Vault}>(), FTVaultFactory.WithdrawFactory())
        factories.updateFactory(Type<&{FungibleToken.Vault}>(), FTVaultFactory.Factory())

        // 2. filter: allow all (the parent is the same person, using their own wallet)
        if acct.storage.borrow<&AnyResource>(from: CapabilityFilter.StoragePath) == nil {
            acct.storage.save(<-CapabilityFilter.createFilter(Type<@CapabilityFilter.AllowAllFilter>()), to: CapabilityFilter.StoragePath)
        }
        if !acct.capabilities.get<&{CapabilityFilter.Filter}>(CapabilityFilter.PublicPath).check() {
            acct.capabilities.unpublish(CapabilityFilter.PublicPath)
            acct.capabilities.publish(acct.capabilities.storage.issue<&{CapabilityFilter.Filter}>(CapabilityFilter.StoragePath), at: CapabilityFilter.PublicPath)
        }

        // 3. OwnedAccount (wraps an &Account capability of this account; keys untouched)
        if acct.storage.borrow<&HybridCustody.OwnedAccount>(from: HybridCustody.OwnedAccountStoragePath) == nil {
            let acctCap = acct.capabilities.account.issue<auth(Storage, Contracts, Keys, Inbox, Capabilities) &Account>()
            acct.storage.save(<-HybridCustody.createOwnedAccount(acct: acctCap), to: HybridCustody.OwnedAccountStoragePath)
            for c in acct.capabilities.storage.getControllers(forPath: HybridCustody.OwnedAccountStoragePath) {
                c.delete()
            }
            acct.capabilities.storage.issue<&{HybridCustody.BorrowableAccount, HybridCustody.OwnedAccountPublic, ViewResolver.Resolver}>(HybridCustody.OwnedAccountStoragePath)
            acct.capabilities.unpublish(HybridCustody.OwnedAccountPublicPath)
            acct.capabilities.publish(
                acct.capabilities.storage.issue<&{HybridCustody.OwnedAccountPublic, ViewResolver.Resolver}>(HybridCustody.OwnedAccountStoragePath),
                at: HybridCustody.OwnedAccountPublicPath
            )
        }
        let owned = acct.storage.borrow<auth(HybridCustody.Owner) &HybridCustody.OwnedAccount>(from: HybridCustody.OwnedAccountStoragePath)
            ?? panic("owned account not found")
        owned.setDisplay(MetadataViews.Display(name: name, description: description, thumbnail: MetadataViews.HTTPFile(url: thumbnail)))

        // 4. publish to the parent's inbox
        let factory = acct.capabilities.get<&CapabilityFactory.Manager>(CapabilityFactory.PublicPath)
        let filter = acct.capabilities.get<&{CapabilityFilter.Filter}>(CapabilityFilter.PublicPath)
        owned.publishToParent(parentAddress: parent, factory: factory, filter: filter)
    }
}
