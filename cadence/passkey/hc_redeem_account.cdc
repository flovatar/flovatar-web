// Wallet-signed (the parent: Flow Wallet / Dapper, pays its own small fee): claim the Flovatar passkey account that was
// published to this wallet and add it to the wallet's HybridCustody Manager (shows up under "Linked accounts").
import ViewResolver from 0xViewResolver
import HybridCustody from 0xHybridCustody

transaction(childAddress: Address) {
    prepare(acct: auth(Storage, Capabilities, Inbox) &Account) {
        if acct.storage.borrow<&HybridCustody.Manager>(from: HybridCustody.ManagerStoragePath) == nil {
            let m <- HybridCustody.createManager(filter: nil)
            acct.storage.save(<- m, to: HybridCustody.ManagerStoragePath)
            for c in acct.capabilities.storage.getControllers(forPath: HybridCustody.ManagerStoragePath) {
                c.delete()
            }
            acct.capabilities.unpublish(HybridCustody.ManagerPublicPath)
            acct.capabilities.publish(
                acct.capabilities.storage.issue<&{HybridCustody.ManagerPublic}>(HybridCustody.ManagerStoragePath),
                at: HybridCustody.ManagerPublicPath
            )
            acct.capabilities.storage.issue<auth(HybridCustody.Manage) &{HybridCustody.ManagerPrivate, HybridCustody.ManagerPublic}>(HybridCustody.ManagerStoragePath)
        }
        let inboxName = HybridCustody.getChildAccountIdentifier(acct.address)
        let cap = acct.inbox.claim<auth(HybridCustody.Child) &{HybridCustody.AccountPrivate, HybridCustody.AccountPublic, ViewResolver.Resolver}>(inboxName, provider: childAddress)
            ?? panic("No link from this Flovatar account is waiting for this wallet")
        let manager = acct.storage.borrow<auth(HybridCustody.Manage) &HybridCustody.Manager>(from: HybridCustody.ManagerStoragePath)
            ?? panic("manager not found")
        manager.addAccount(cap: cap)
    }
}
