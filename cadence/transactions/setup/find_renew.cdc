import FUSD from 0x3c5959b568896393
import FIND from 0x097bafa4e0b48eef

transaction(name: String, amount: UFix64) {

    let price : UFix64
    let vaultRef : &FUSD.Vault?
    let finLeases : &FIND.LeaseCollection?

    prepare(acct: AuthAccount) {
        self.price=FIND.calculateCost(name)
        self.vaultRef = acct.borrow<&FUSD.Vault>(from: /storage/fusdVault)
        self.finLeases= acct.borrow<&FIND.LeaseCollection>(from:FIND.LeaseStoragePath)
    }

    pre{
        self.price == amount : "expected renew cost : ".concat(self.price.toString()).concat(" is not the same as calculated renew cost : ").concat(amount.toString())
        self.vaultRef != nil : "Could not borrow reference to the fusdVault!"
        self.finLeases != nil : "Could not borrow reference to find lease collection"
    }

    execute{
        let payVault <- self.vaultRef!.withdraw(amount: self.price) as! @FUSD.Vault
        let finToken= self.finLeases!.borrow(name)
        finToken.extendLease(<- payVault)
    }
}
