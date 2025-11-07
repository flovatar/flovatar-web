//import Toucans from 0x577a3c409c5dcb5e
//import NFTCatalog from 0x49a7cda3a1eecc29
//import NonFungibleToken from 0x1d7e57aa55817448
import Toucans from "../../contracts/toucans/Toucans.cdc"
import NFTCatalog from "../../contracts/toucans/utils/NFTCatalog.cdc"
import NonFungibleToken from "../../contracts/NonFungibleToken.cdc"

transaction(projectOwner: Address, projectId: String, nftIDs: [UInt64], collectionIdentifier: String, message: String) {

  let Project: &Toucans.Project{Toucans.ProjectPublic}
  let Collection: &NonFungibleToken.Collection
  let Sender: Address
  let CatalogEntry: NFTCatalog.NFTCatalogMetadata

  prepare(user: AuthAccount) {
    let projectCollection = getAccount(projectOwner).getCapability(Toucans.CollectionPublicPath)
                  .borrow<&Toucans.Collection{Toucans.CollectionPublic}>()
                  ?? panic("This is an incorrect address for project owner.")
    self.Project = projectCollection.borrowProjectPublic(projectId: projectId)
                  ?? panic("Project does not exist, at least in this collection.")

    self.CatalogEntry = NFTCatalog.getCatalogEntry(collectionIdentifier: collectionIdentifier)
          ?? panic("There is no NFT Catalog entry for this.")
    let storagePath: StoragePath = self.CatalogEntry.collectionData.storagePath

    self.Collection = user.borrow<&NonFungibleToken.Collection>(from: storagePath)
              ?? panic("User does not have this NFT Collection set up.")
    self.Sender = user.address
  }

  execute {
    let nftContract = getAccount(self.CatalogEntry.contractAddress).contracts.borrow<&NonFungibleToken>(name: self.CatalogEntry.contractName)!
    let tempCollection <- nftContract.createEmptyCollection()
    for id in nftIDs {
      tempCollection.deposit(token: <- self.Collection.withdraw(withdrawID: id))
    }
    self.Project.donateNFTToTreasury(collection: <- tempCollection, sender: self.Sender, message: message)
  }
}
