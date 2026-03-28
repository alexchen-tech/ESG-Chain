import { Module } from '@nestjs/common'
import { TradeGoodsController } from './trade-goods.controller'
import { TradeGoodsService } from './trade-goods.service'

@Module({ controllers: [TradeGoodsController], providers: [TradeGoodsService] })
export class TradeGoodsModule {}
